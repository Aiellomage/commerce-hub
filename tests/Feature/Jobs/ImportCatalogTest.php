<?php

namespace Tests\Feature\Jobs;

use App\Enums\SyncRunStatus;
use App\Enums\SyncRunType;
use App\Jobs\ImportCatalog;
use App\Models\Product;
use App\Models\Store;
use App\Models\SyncRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class ImportCatalogTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        Sleep::fake();

        $this->store = Store::factory()->create(['magento_url' => 'https://magento.test']);
    }

    public function test_magento_products_are_imported(): void
    {
        $this->fakeCatalog($this->fixture('product-simple'), $this->fixture('product-configurable'));

        $syncRun = $this->runImport();

        $this->assertSame(2, $this->store->products()->count());

        $product = $this->store->products()->where('sku', '24-MB01')->sole();
        $this->assertSame(1, $product->magento_id);
        $this->assertSame('Joust Duffle Bag', $product->name);
        $this->assertSame('34.0000', $product->price);
        $this->assertSame(0, $product->qty);
        $this->assertTrue($product->is_enabled);
        $this->assertSame('/m/b/mb01-blue-0.jpg', $product->attributes['image']);
        $this->assertSame('2026-09-30 22:45:55', $product->magento_updated_at->format('Y-m-d H:i:s'));

        $this->assertSame(SyncRunStatus::Completed, $syncRun->status);
        $this->assertSame(2, $syncRun->items_processed);
        $this->assertSame(2, $syncRun->items_total);
    }

    public function test_running_the_import_twice_does_not_duplicate_products(): void
    {
        $this->fakeCatalog($this->fixture('product-simple'), $this->fixture('product-configurable'));

        $this->runImport();
        $this->runImport();

        $this->assertSame(2, $this->store->products()->count());
    }

    public function test_existing_products_are_updated_without_touching_the_stock(): void
    {
        Product::factory()->for($this->store)->create([
            'magento_id' => 1,
            'sku' => '24-MB01',
            'name' => 'Old name',
            'qty' => 7,
        ]);
        $this->fakeCatalog($this->fixture('product-simple'));

        $this->runImport();

        $product = $this->store->products()->sole();
        $this->assertSame('Joust Duffle Bag', $product->name);
        $this->assertSame(7, $product->qty);
    }

    public function test_a_product_recreated_in_magento_with_the_same_sku_takes_over_the_existing_row(): void
    {
        $existing = Product::factory()->for($this->store)->create(['magento_id' => 10, 'sku' => '24-MB01']);
        $this->fakeCatalog(['id' => 55] + $this->fixture('product-simple'));

        $this->runImport();

        $this->assertSame(1, $this->store->products()->count());
        $this->assertSame(55, $existing->fresh()->magento_id);
    }

    public function test_products_of_other_stores_are_left_alone(): void
    {
        $otherStoreProduct = Product::factory()->create(['magento_id' => 1, 'sku' => '24-MB01', 'name' => 'Other store']);
        $this->fakeCatalog($this->fixture('product-simple'));

        $this->runImport();

        $this->assertSame('Other store', $otherStoreProduct->fresh()->name);
        $this->assertSame(1, $this->store->products()->count());
    }

    public function test_the_sync_run_is_marked_as_failed_when_magento_rejects_the_request(): void
    {
        Http::fake(['magento.test/rest/V1/products*' => Http::response(['message' => 'The consumer isn\'t authorized'], 401)]);
        $syncRun = $this->pendingSyncRun();

        try {
            ImportCatalog::dispatch($syncRun);
            $this->fail('The import must rethrow the Magento error.');
        } catch (RequestException) {
            // The sync queue calls failed() before rethrowing.
        }

        $syncRun->refresh();
        $this->assertSame(SyncRunStatus::Failed, $syncRun->status);
        $this->assertStringContainsString('401', $syncRun->error_message);
        $this->assertNotNull($syncRun->finished_at);
        $this->assertSame(0, $this->store->products()->count());
    }

    private function runImport(): SyncRun
    {
        $syncRun = $this->pendingSyncRun();

        ImportCatalog::dispatch($syncRun);

        return $syncRun->refresh();
    }

    private function pendingSyncRun(): SyncRun
    {
        return SyncRun::factory()->for($this->store)->pending()->create([
            'type' => SyncRunType::CatalogImport,
            'items_total' => 0,
        ]);
    }

    /**
     * @param  array<string, mixed>  ...$products
     */
    private function fakeCatalog(array ...$products): void
    {
        Http::fake(['magento.test/rest/V1/products*' => Http::response([
            'items' => $products,
            'total_count' => count($products),
        ])]);
    }

    /**
     * @return array<string, mixed>
     */
    private function fixture(string $name): array
    {
        return json_decode(file_get_contents(base_path("tests/Fixtures/Magento/{$name}.json")), true);
    }
}
