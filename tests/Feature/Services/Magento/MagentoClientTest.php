<?php

namespace Tests\Feature\Services\Magento;

use App\Models\Store;
use App\Services\Magento\Data\MagentoProduct;
use App\Services\Magento\MagentoClient;
use App\Services\Magento\MagentoClientFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class MagentoClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Sleep::fake();
    }

    public function test_products_are_returned_as_data_objects(): void
    {
        Http::fake(['magento.test/rest/V1/products*' => Http::response([
            'items' => [$this->fixture('product-simple')],
            'total_count' => 1,
        ])]);

        $result = $this->client()->products();

        $this->assertSame(1, $result['total_count']);
        $this->assertInstanceOf(MagentoProduct::class, $result['items'][0]);
        $this->assertSame('24-MB01', $result['items'][0]->sku);
    }

    public function test_every_request_is_signed_with_oauth(): void
    {
        Http::fake(['*' => Http::response(['items' => [], 'total_count' => 0])]);

        $this->client()->products();

        Http::assertSent(fn (Request $request) => str_starts_with($request->header('Authorization')[0] ?? '', 'OAuth ')
            && str_contains($request->header('Authorization')[0], 'oauth_signature_method="HMAC-SHA256"'));
    }

    public function test_pagination_stops_at_total_count_even_if_magento_repeats_the_last_page(): void
    {
        $page = ['items' => [$this->fixture('product-simple'), $this->fixture('product-simple')], 'total_count' => 5];
        Http::fake(['*' => Http::response($page)]);

        $products = $this->client()->allProducts(pageSize: 2)->all();

        Http::assertSentCount(3);
        $this->assertCount(6, $products);
    }

    public function test_incremental_filter_uses_gteq_on_updated_at_in_utc(): void
    {
        Http::fake(['*' => Http::response(['items' => [], 'total_count' => 0])]);

        $this->client()->products(updatedSince: now()->setTimezone('Europe/Rome')->setDateTime(2026, 10, 1, 2, 0));

        Http::assertSent(function (Request $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return $query['searchCriteria']['filterGroups'][0]['filters'][0] === [
                'field' => 'updated_at',
                'value' => '2026-10-01 00:00:00',
                'conditionType' => 'gteq',
            ];
        });
    }

    public function test_results_are_sorted_by_updated_at_with_entity_id_as_tie_breaker(): void
    {
        Http::fake(['*' => Http::response(['items' => [], 'total_count' => 0])]);

        $this->client()->products();

        Http::assertSent(function (Request $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return $query['searchCriteria']['sortOrders'] === [
                ['field' => 'updated_at', 'direction' => 'ASC'],
                ['field' => 'entity_id', 'direction' => 'ASC'],
            ];
        });
    }

    public function test_temporary_errors_are_retried(): void
    {
        Http::fake(['*' => Http::sequence()
            ->push(['message' => 'Service Unavailable'], 503)
            ->push(['items' => [], 'total_count' => 0])]);

        $this->assertSame(0, $this->client()->products()['total_count']);
        Http::assertSentCount(2);
    }

    public function test_authentication_errors_fail_immediately(): void
    {
        Http::fake(['*' => Http::response(['message' => 'The consumer isn\'t authorized'], 401)]);

        try {
            $this->client()->products();
            $this->fail('A 401 must throw a RequestException.');
        } catch (RequestException $exception) {
            $this->assertSame(401, $exception->response->status());
        }

        Http::assertSentCount(1);
    }

    public function test_factory_builds_a_client_for_the_store(): void
    {
        $store = Store::factory()->make(['magento_url' => 'https://magento.test']);

        $client = $this->app->make(MagentoClientFactory::class)->forStore($store);

        $this->assertInstanceOf(MagentoClient::class, $client);
        $this->assertSame($this->app->make(MagentoClientFactory::class), $this->app->make(MagentoClientFactory::class));
    }

    private function client(): MagentoClient
    {
        return new MagentoClient('https://magento.test', [
            'consumer_key' => 'ck',
            'consumer_secret' => 'cs',
            'access_token' => 'at',
            'access_token_secret' => 'ats',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function fixture(string $name): array
    {
        return json_decode(file_get_contents(base_path("tests/Fixtures/Magento/{$name}.json")), true);
    }
}
