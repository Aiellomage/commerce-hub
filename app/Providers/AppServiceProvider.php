<?php

namespace App\Providers;

use App\Services\Magento\MagentoClientFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(MagentoClientFactory::class, function (): MagentoClientFactory {
            $caBundle = config('services.magento.ca_bundle');

            return new MagentoClientFactory(
                verify: $caBundle ? base_path($caBundle) : true,
                timeout: config('services.magento.timeout'),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());
    }
}
