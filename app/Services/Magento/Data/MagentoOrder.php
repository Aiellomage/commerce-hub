<?php

namespace App\Services\Magento\Data;

use App\Services\Magento\Data\Casts\DecimalCast;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;

/**
 * An order as returned by GET /V1/orders, translated into the Hub's vocabulary.
 */
class MagentoOrder extends Data
{
    /**
     * @param  array<int, MagentoOrderItem>  $items
     */
    public function __construct(
        #[MapInputName('entity_id')]
        public int $magentoId,

        #[MapInputName('increment_id')]
        public string $incrementId,

        public string $status,

        #[MapInputName('customer_email')]
        public string $customerEmail,

        #[MapInputName('grand_total'), WithCast(DecimalCast::class)]
        public string $grandTotal,

        #[MapInputName('order_currency_code')]
        public string $currency,

        #[MapInputName('created_at'), WithCast(DateTimeInterfaceCast::class, format: 'Y-m-d H:i:s', timeZone: 'UTC')]
        public CarbonImmutable $placedAt,

        #[MapInputName('updated_at'), WithCast(DateTimeInterfaceCast::class, format: 'Y-m-d H:i:s', timeZone: 'UTC')]
        public CarbonImmutable $updatedAt,

        #[DataCollectionOf(MagentoOrderItem::class)]
        public array $items,

        #[MapInputName('customer_firstname')]
        public ?string $customerFirstname = null,

        #[MapInputName('customer_lastname')]
        public ?string $customerLastname = null,
    ) {}

    /**
     * Guests have no customer name on the order: the result is null in that case.
     */
    public function customerName(): ?string
    {
        $name = trim("{$this->customerFirstname} {$this->customerLastname}");

        return $name === '' ? null : $name;
    }

    /**
     * The rows that represent what was actually sold, without configurable children.
     *
     * @return array<int, MagentoOrderItem>
     */
    public function lineItems(): array
    {
        return array_values(array_filter($this->items, fn (MagentoOrderItem $item) => $item->isTopLevel()));
    }
}
