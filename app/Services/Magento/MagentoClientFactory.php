<?php

namespace App\Services\Magento;

use App\Models\Store;

/**
 * Builds a MagentoClient for a store: the store is only known at runtime, the HTTP settings come from config.
 */
class MagentoClientFactory
{
    /**
     * @param  bool|string  $verify  true to use the system CA bundle, or the path of a custom CA bundle
     */
    public function __construct(
        private bool|string $verify = true,
        private int $timeout = 30,
    ) {}

    public function forStore(Store $store): MagentoClient
    {
        return new MagentoClient(
            baseUrl: $store->magento_url,
            credentials: $store->magento_credentials,
            verify: $this->verify,
            timeout: $this->timeout,
        );
    }
}
