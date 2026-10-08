<?php

namespace App\Services\Magento\Data;

use App\Services\Magento\Data\Casts\CustomAttributesCast;
use App\Services\Magento\Data\Casts\DecimalCast;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;

/**
 * A product as returned by GET /V1/products, translated into the Hub's vocabulary.
 */
class MagentoProduct extends Data
{
    /**
     * @param  array<string, mixed>  $attributes  custom_attributes indexed by attribute code
     */
    public function __construct(
        #[MapInputName('id')]
        public int $magentoId,

        public string $sku,

        public string $name,

        #[MapInputName('type_id')]
        public string $type,

        public int $status,

        #[MapInputName('updated_at'), WithCast(DateTimeInterfaceCast::class, format: 'Y-m-d H:i:s', timeZone: 'UTC')]
        public CarbonImmutable $updatedAt,

        #[MapInputName('custom_attributes'), WithCast(CustomAttributesCast::class)]
        public array $attributes = [],

        /** Grouped products have no price of their own: Magento omits the field. */
        #[WithCast(DecimalCast::class)]
        public ?string $price = null,
    ) {}

    /**
     * Magento uses status 1 for enabled and 2 for disabled.
     */
    public function isEnabled(): bool
    {
        return $this->status === 1;
    }

    public function attribute(string $code): mixed
    {
        return $this->attributes[$code] ?? null;
    }

    public function urlKey(): ?string
    {
        return $this->attribute('url_key');
    }
}
