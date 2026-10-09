<?php

namespace App\Console\Commands;

use App\Enums\SyncRunType;
use App\Jobs\ImportCatalog;
use App\Models\Store;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('catalog:import {store : The code of the store to import} {--since= : Only import products updated since this date}')]
#[Description('Queue an import of the Magento catalog of a store')]
class ImportCatalogCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $store = Store::query()->where('code', $this->argument('store'))->first();

        if ($store === null) {
            $this->error("Store [{$this->argument('store')}] not found.");

            return self::FAILURE;
        }

        if (! $store->is_active) {
            $this->error("Store [{$store->code}] is not active.");

            return self::FAILURE;
        }

        $updatedSince = $this->option('since') !== null ? CarbonImmutable::parse($this->option('since')) : null;

        $syncRun = $store->syncRuns()->create(['type' => SyncRunType::CatalogImport]);

        ImportCatalog::dispatch($syncRun, $updatedSince);

        $this->info("Catalog import #{$syncRun->id} queued for store [{$store->code}].");

        return self::SUCCESS;
    }
}
