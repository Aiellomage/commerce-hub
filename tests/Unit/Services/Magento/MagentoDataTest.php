<?php

namespace Tests\Unit\Services\Magento;

use App\Services\Magento\Data\MagentoOrder;
use App\Services\Magento\Data\MagentoProduct;
use Tests\TestCase;

class MagentoDataTest extends TestCase
{
    public function test_product_is_mapped_from_a_real_magento_payload(): void
    {
        $product = MagentoProduct::from($this->fixture('product-simple'));

        $this->assertSame(1, $product->magentoId);
        $this->assertSame('24-MB01', $product->sku);
        $this->assertSame('simple', $product->type);
        $this->assertSame('34.0000', $product->price);
        $this->assertTrue($product->isEnabled());
        $this->assertSame('2026-09-30 22:45:55', $product->updatedAt->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $product->updatedAt->getTimezone()->getName());
        $this->assertSame('joust-duffle-bag', $product->urlKey());
    }

    public function test_product_disabled_on_magento_is_not_enabled(): void
    {
        $product = MagentoProduct::from(['status' => 2] + $this->fixture('product-simple'));

        $this->assertFalse($product->isEnabled());
    }

    public function test_decimal_prices_keep_their_exact_value(): void
    {
        $product = MagentoProduct::from(['price' => 19.99] + $this->fixture('product-simple'));

        $this->assertSame('19.9900', $product->price);
    }

    public function test_grouped_product_without_price_is_accepted(): void
    {
        $payload = ['type_id' => 'grouped'] + $this->fixture('product-simple');
        unset($payload['price']);

        $this->assertNull(MagentoProduct::from($payload)->price);
    }

    public function test_order_is_mapped_from_a_real_magento_payload(): void
    {
        $order = MagentoOrder::from($this->fixture('order'));

        $this->assertSame('000000001', $order->incrementId);
        $this->assertSame('Veronica Costello', $order->customerName());
        $this->assertSame('36.3900', $order->grandTotal);
        $this->assertSame('USD', $order->currency);
        $this->assertCount(1, $order->lineItems());
        $this->assertSame('WS03-XS-Red', $order->lineItems()[0]->sku);
        $this->assertSame(1, $order->lineItems()[0]->qty);
    }

    public function test_configurable_children_are_not_line_items(): void
    {
        $payload = $this->fixture('order');
        $parent = $payload['items'][0];
        $payload['items'][] = [
            'item_id' => 99,
            'parent_item_id' => $parent['item_id'],
            'product_type' => 'simple',
            'price' => 0,
            'row_total' => 0,
        ] + $parent;

        $order = MagentoOrder::from($payload);

        $this->assertCount(2, $order->items);
        $this->assertCount(1, $order->lineItems());
        $this->assertSame($parent['item_id'], $order->lineItems()[0]->magentoItemId);
    }

    public function test_guest_order_has_no_customer_name(): void
    {
        $payload = $this->fixture('order');
        unset($payload['customer_firstname'], $payload['customer_lastname']);

        $this->assertNull(MagentoOrder::from($payload)->customerName());
    }

    /**
     * @return array<string, mixed>
     */
    private function fixture(string $name): array
    {
        return json_decode(file_get_contents(base_path("tests/Fixtures/Magento/{$name}.json")), true);
    }
}
