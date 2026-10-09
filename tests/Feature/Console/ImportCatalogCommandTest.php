<?php

namespace Tests\Feature\Console;

use App\Enums\SyncRunStatus;
use App\Enums\SyncRunType;
use App\Jobs\ImportCatalog;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ImportCatalogCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    public function test_a_full_import_is_queued_with_a_pending_sync_run(): void
    {
        $store = Store::factory()->create(['code' => 'it']);

        $this->artisan('catalog:import', ['store' => 'it'])->assertSuccessful();

        $syncRun = $store->syncRuns()->sole();
        $this->assertSame(SyncRunType::CatalogImport, $syncRun->type);
        $this->assertSame(SyncRunStatus::Pending, $syncRun->status);

        Queue::assertPushed(ImportCatalog::class, fn (ImportCatalog $job) => $job->syncRun->is($syncRun) && $job->updatedSince === null);
    }

    public function test_the_since_option_queues_an_incremental_import(): void
    {
        Store::factory()->create(['code' => 'it']);

        $this->artisan('catalog:import', ['store' => 'it', '--since' => '2026-10-01 00:00:00'])->assertSuccessful();

        Queue::assertPushed(ImportCatalog::class, fn (ImportCatalog $job) => $job->updatedSince?->toDateTimeString() === '2026-10-01 00:00:00');
    }

    public function test_unknown_stores_are_rejected(): void
    {
        $this->artisan('catalog:import', ['store' => 'missing'])->assertFailed();

        Queue::assertNothingPushed();
    }

    public function test_inactive_stores_are_not_imported(): void
    {
        $store = Store::factory()->inactive()->create(['code' => 'it']);

        $this->artisan('catalog:import', ['store' => 'it'])->assertFailed();

        Queue::assertNothingPushed();
        $this->assertSame(0, $store->syncRuns()->count());
    }
}
