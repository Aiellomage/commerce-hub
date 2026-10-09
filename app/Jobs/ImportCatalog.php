<?php

namespace App\Jobs;

use App\Models\Product;
use App\Models\SyncRun;
use App\Services\Magento\Data\MagentoProduct;
use App\Services\Magento\MagentoClientFactory;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\LazyCollection;
use Throwable;

/**
 * Imports the Magento catalog of a store into the products table, one chunk of products per query.
 *
 * Running it twice gives the same result: rows are matched on (store_id, magento_id) or (store_id, sku)
 * and updated in place, so a retry or an overlapping incremental window never creates duplicates.
 */
class ImportCatalog implements ShouldQueue
{
    use Queueable;

    /**
     * Products written to the database in a single upsert.
     */
    public const int CHUNK_SIZE = 500;

    /**
     * Columns refreshed when the product already exists. qty is left out on purpose: stock is owned by the ERP.
     *
     * @var list<string>
     */
    private const array UPDATED_COLUMNS = [
        'magento_id',
        'sku',
        'name',
        'price',
        'is_enabled',
        'attributes',
        'magento_updated_at',
    ];

    /**
     * @param  CarbonImmutable|null  $updatedSince  only import products changed since this date, null for a full import
     */
    public function __construct(
        public SyncRun $syncRun,
        public ?CarbonImmutable $updatedSince = null,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(MagentoClientFactory $clientFactory): void
    {
        $this->syncRun->markRunning();

        $clientFactory->forStore($this->syncRun->store)
            ->allProducts(updatedSince: $this->updatedSince)
            ->chunk(self::CHUNK_SIZE)
            ->each(function (LazyCollection $products): void {
                Product::upsert(
                    $products->map(fn (MagentoProduct $product) => $this->toRow($product))->values()->all(),
                    uniqueBy: ['store_id', 'magento_id'],
                    update: self::UPDATED_COLUMNS,
                );

                $this->syncRun->increment('items_processed', $products->count());
            });

        $this->syncRun->update(['items_total' => $this->syncRun->items_processed]);
        $this->syncRun->markCompleted();
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        $this->syncRun->markFailed($exception->getMessage());
    }

    /**
     * Turn a Magento product into a products row.
     *
     * upsert() talks to the query builder directly, so model casts are not applied:
     * JSON and dates must already be in their database format.
     *
     * @return array<string, mixed>
     */
    private function toRow(MagentoProduct $product): array
    {
        return [
            'store_id' => $this->syncRun->store_id,
            'magento_id' => $product->magentoId,
            'sku' => $product->sku,
            'name' => $product->name,
            'price' => $product->price ?? '0',
            'is_enabled' => $product->isEnabled(),
            'attributes' => json_encode($product->attributes, JSON_THROW_ON_ERROR),
            'magento_updated_at' => $product->updatedAt->format('Y-m-d H:i:s'),
        ];
    }
}
