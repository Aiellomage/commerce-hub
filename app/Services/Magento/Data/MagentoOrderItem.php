<?php

namespace App\Services\Magento\Data;

use App\Services\Magento\Data\Casts\DecimalCast;
use App\Services\Magento\Data\Casts\QuantityCast;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Data;

class MagentoOrderItem extends Data
{
    public function __construct(
        #[MapInputName('item_id')]
        public int $magentoItemId,

        public string $sku,

        public string $name,

        #[MapInputName('product_type')]
        public string $type,

        #[MapInputName('qty_ordered'), WithCast(QuantityCast::class)]
        public int $qty,

        #[WithCast(DecimalCast::class)]
        public string $price,

        #[MapInputName('row_total'), WithCast(DecimalCast::class)]
        public string $rowTotal,

        #[MapInputName('product_id')]
        public ?int $magentoProductId = null,

        #[MapInputName('parent_item_id')]
        public ?int $parentItemId = null,
    ) {}

    /**
     * Child rows of a configurable repeat their parent with price 0: only top-level rows are real sales.
     */
    public function isTopLevel(): bool
    {
        return $this->parentItemId === null;
    }
}
