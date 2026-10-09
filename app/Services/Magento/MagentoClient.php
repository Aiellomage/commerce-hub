<?php

namespace App\Services\Magento;

use App\Services\Magento\Data\MagentoOrder;
use App\Services\Magento\Data\MagentoProduct;
use Carbon\CarbonInterface;
use GuzzleHttp\Subscriber\Oauth\Oauth1;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\LazyCollection;
use Throwable;

/**
 * Talks to the Magento 2 REST API of a single store, signing every request with OAuth 1.0a.
 */
class MagentoClient
{
    /**
     * @param  array{consumer_key: string, consumer_secret: string, access_token: string, access_token_secret: string}  $credentials
     * @param  bool|string  $verify  true to use the system CA bundle, or the path of a custom CA bundle
     */
    public function __construct(
        private string $baseUrl,
        #[\SensitiveParameter] private array $credentials,
        private bool|string $verify = true,
        private int $timeout = 30,
    ) {}

    /**
     * Fetch one page of products, optionally only those updated since the given date.
     *
     * @return array{items: array<int, MagentoProduct>, total_count: int}
     */
    public function products(int $page = 1, int $pageSize = 100, ?CarbonInterface $updatedSince = null): array
    {
        $result = $this->search('products', $page, $pageSize, $updatedSince);

        return ['items' => MagentoProduct::collect($result['items']), 'total_count' => $result['total_count']];
    }

    /**
     * Fetch one page of orders, optionally only those updated since the given date.
     *
     * @return array{items: array<int, MagentoOrder>, total_count: int}
     */
    public function orders(int $page = 1, int $pageSize = 100, ?CarbonInterface $updatedSince = null): array
    {
        $result = $this->search('orders', $page, $pageSize, $updatedSince);

        return ['items' => MagentoOrder::collect($result['items']), 'total_count' => $result['total_count']];
    }

    /**
     * Iterate over every product, loading one page at a time.
     *
     * @return LazyCollection<int, MagentoProduct>
     */
    public function allProducts(int $pageSize = 100, ?CarbonInterface $updatedSince = null): LazyCollection
    {
        return $this->paginate('products', $pageSize, $updatedSince)
            ->map(fn (array $item) => MagentoProduct::from($item));
    }

    /**
     * Iterate over every order, loading one page at a time.
     *
     * @return LazyCollection<int, MagentoOrder>
     */
    public function allOrders(int $pageSize = 100, ?CarbonInterface $updatedSince = null): LazyCollection
    {
        return $this->paginate('orders', $pageSize, $updatedSince)
            ->map(fn (array $item) => MagentoOrder::from($item));
    }

    /**
     * Walk through all the pages of a search endpoint.
     *
     * The number of pages comes from total_count: older Magento versions return the last page
     * again when currentPage is out of range, so "loop until a page is empty" would never stop.
     *
     * @return LazyCollection<int, array<string, mixed>>
     */
    private function paginate(string $endpoint, int $pageSize, ?CarbonInterface $updatedSince): LazyCollection
    {
        return LazyCollection::make(function () use ($endpoint, $pageSize, $updatedSince) {
            $page = 1;

            do {
                $result = $this->search($endpoint, $page, $pageSize, $updatedSince);

                // A plain yield keeps keys growing across pages; "yield from" would restart them at 0
                // on every page and ->all() would silently keep only the last page.
                foreach ($result['items'] as $item) {
                    yield $item;
                }

                $lastPage = (int) ceil($result['total_count'] / $pageSize);
                $page++;
            } while ($page <= $lastPage);
        });
    }

    /**
     * Call a Magento search endpoint ordered by updated_at, oldest first.
     *
     * entity_id breaks the ties: many rows share the same updated_at (an import, a mass action) and MySQL
     * returns ties in no fixed order, so with OFFSET pagination some rows would show up on two pages and others on none.
     *
     * @return array{items: array<int, array<string, mixed>>, total_count: int}
     */
    private function search(string $endpoint, int $page, int $pageSize, ?CarbonInterface $updatedSince): array
    {
        $query = [
            'searchCriteria[sortOrders][0][field]' => 'updated_at',
            'searchCriteria[sortOrders][0][direction]' => 'ASC',
            'searchCriteria[sortOrders][1][field]' => 'entity_id',
            'searchCriteria[sortOrders][1][direction]' => 'ASC',
            'searchCriteria[pageSize]' => $pageSize,
            'searchCriteria[currentPage]' => $page,
        ];

        if ($updatedSince !== null) {
            $query += [
                'searchCriteria[filterGroups][0][filters][0][field]' => 'updated_at',
                'searchCriteria[filterGroups][0][filters][0][value]' => $updatedSince->copy()->utc()->format('Y-m-d H:i:s'),
                'searchCriteria[filterGroups][0][filters][0][conditionType]' => 'gteq',
            ];
        }

        $response = $this->request()->get($endpoint, $query);

        return [
            'items' => $response->json('items') ?? [],
            'total_count' => (int) $response->json('total_count'),
        ];
    }

    /**
     * Build a signed request that retries temporary failures and throws on any other error.
     */
    private function request(): PendingRequest
    {
        return Http::baseUrl(rtrim($this->baseUrl, '/').'/rest/V1')
            ->withMiddleware(new Oauth1([
                'consumer_key' => $this->credentials['consumer_key'],
                'consumer_secret' => $this->credentials['consumer_secret'],
                'token' => $this->credentials['access_token'],
                'token_secret' => $this->credentials['access_token_secret'],
                'signature_method' => Oauth1::SIGNATURE_METHOD_HMACSHA256,
            ]))
            ->withOptions(['auth' => 'oauth', 'verify' => $this->verify])
            ->acceptJson()
            ->timeout($this->timeout)
            ->retry(3, fn (int $attempt) => $attempt * 500, fn (Throwable $exception) => $this->isTemporary($exception))
            ->throw();
    }

    /**
     * Determine if a failed request is worth retrying (network errors, rate limiting, server errors).
     */
    private function isTemporary(Throwable $exception): bool
    {
        if ($exception instanceof ConnectionException) {
            return true;
        }

        return $exception instanceof RequestException
            && ($exception->response->serverError() || $exception->response->status() === 429);
    }
}
