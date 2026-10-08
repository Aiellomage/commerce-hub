<?php

namespace App\Services\Magento;

use App\Models\Store;
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
     * Create a client for the given store, using the HTTP settings from config/services.php.
     */
    public static function forStore(Store $store): self
    {
        $caBundle = config('services.magento.ca_bundle');

        return new self(
            baseUrl: $store->magento_url,
            credentials: $store->magento_credentials,
            verify: $caBundle ? base_path($caBundle) : true,
            timeout: config('services.magento.timeout'),
        );
    }

    /**
     * Fetch one page of products, optionally only those updated since the given date.
     *
     * @return array{items: array<int, array<string, mixed>>, total_count: int}
     */
    public function products(int $page = 1, int $pageSize = 100, ?CarbonInterface $updatedSince = null): array
    {
        return $this->search('products', $page, $pageSize, $updatedSince);
    }

    /**
     * Fetch one page of orders, optionally only those updated since the given date.
     *
     * @return array{items: array<int, array<string, mixed>>, total_count: int}
     */
    public function orders(int $page = 1, int $pageSize = 100, ?CarbonInterface $updatedSince = null): array
    {
        return $this->search('orders', $page, $pageSize, $updatedSince);
    }

    /**
     * Iterate over every product, loading one page at a time.
     *
     * @return LazyCollection<int, array<string, mixed>>
     */
    public function allProducts(int $pageSize = 100, ?CarbonInterface $updatedSince = null): LazyCollection
    {
        return $this->paginate('products', $pageSize, $updatedSince);
    }

    /**
     * Iterate over every order, loading one page at a time.
     *
     * @return LazyCollection<int, array<string, mixed>>
     */
    public function allOrders(int $pageSize = 100, ?CarbonInterface $updatedSince = null): LazyCollection
    {
        return $this->paginate('orders', $pageSize, $updatedSince);
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

                yield from $result['items'];

                $lastPage = (int) ceil($result['total_count'] / $pageSize);
                $page++;
            } while ($page <= $lastPage);
        });
    }

    /**
     * Call a Magento search endpoint ordered by updated_at, oldest first.
     *
     * @return array{items: array<int, array<string, mixed>>, total_count: int}
     */
    private function search(string $endpoint, int $page, int $pageSize, ?CarbonInterface $updatedSince): array
    {
        $query = [
            'searchCriteria[sortOrders][0][field]' => 'updated_at',
            'searchCriteria[sortOrders][0][direction]' => 'ASC',
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
