<?php

namespace Database\Seeders;

use App\Enums\SyncRunType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Store;
use App\Models\SyncRun;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $stores = collect([
            Store::factory()->create(['name' => 'Negozio Italia', 'code' => 'it_main']),
            Store::factory()->create(['name' => 'Shop Deutschland', 'code' => 'de_main']),
            Store::factory()->inactive()->create(['name' => 'Boutique France', 'code' => 'fr_main']),
        ]);

        $this->seedMagentoDevStore();

        foreach ($stores as $store) {
            $products = Product::factory()->count(50)->for($store)->create();
            Product::factory()->count(5)->disabled()->for($store)->create();
            Product::factory()->count(5)->outOfStock()->for($store)->create();

            $this->seedOrders($store, $products);
            $this->seedSyncRuns($store);
        }
    }

    /**
     * Create the store connected to the local Magento instance, when its credentials are configured.
     */
    private function seedMagentoDevStore(): void
    {
        $config = config('services.magento.dev_store');

        if (blank($config['url']) || blank($config['consumer_key'])) {
            return;
        }

        Store::factory()->create([
            'name' => 'Magento locale',
            'code' => 'magento_dev',
            'magento_url' => $config['url'],
            'magento_credentials' => Arr::except($config, 'url'),
        ]);
    }

    /**
     * Create orders whose items point to real catalog products of the store.
     *
     * @param  Collection<int, Product>  $products
     */
    private function seedOrders(Store $store, Collection $products): void
    {
        $orders = Order::factory()->count(15)->for($store)->create()
            ->merge(Order::factory()->count(10)->exported()->for($store)->create())
            ->merge(Order::factory()->count(2)->exportFailed()->for($store)->create());

        foreach ($orders as $order) {
            foreach ($products->random(fake()->numberBetween(1, 4)) as $product) {
                OrderItem::factory()->for($order)->forProduct($product)->create();
            }

            $order->update(['grand_total' => $order->items()->sum('row_total')]);
        }
    }

    /**
     * Create a realistic history of sync runs for the store.
     */
    private function seedSyncRuns(Store $store): void
    {
        foreach (SyncRunType::cases() as $type) {
            SyncRun::factory()->count(5)->for($store)->create(['type' => $type]);
        }

        SyncRun::factory()->failed()->for($store)->create(['type' => SyncRunType::OrderSync]);
        SyncRun::factory()->running()->for($store)->create(['type' => SyncRunType::CatalogImport]);
    }
}
