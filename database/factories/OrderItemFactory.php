<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'product_id' => null,
            'magento_item_id' => fake()->unique()->numberBetween(1, 10_000_000),
            'sku' => fake()->bothify('SKU-####-??'),
            'name' => fake()->words(3, true),
            'qty' => fake()->numberBetween(1, 5),
            'price' => fake()->randomFloat(4, 1, 200),
            'row_total' => fn (array $attributes) => bcmul((string) $attributes['price'], (string) $attributes['qty'], 4),
        ];
    }

    /**
     * Indicate that the item was sold from the given catalog product.
     */
    public function forProduct(Product $product): static
    {
        return $this->state(fn (array $attributes) => [
            'product_id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'price' => $product->price,
        ]);
    }
}
