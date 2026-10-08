<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'magento_id' => fake()->unique()->numberBetween(1, 1_000_000),
            'sku' => fake()->unique()->bothify('SKU-####-??'),
            'name' => fake()->words(3, true),
            'price' => fake()->randomFloat(4, 1, 500),
            'qty' => fake()->numberBetween(0, 200),
            'is_enabled' => true,
            'attributes' => [
                'color' => fake()->safeColorName(),
                'weight' => fake()->randomFloat(2, 0.1, 20),
            ],
            'magento_updated_at' => fake()->dateTimeBetween('-1 month'),
        ];
    }

    /**
     * Indicate that the product is disabled on Magento.
     */
    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_enabled' => false,
        ]);
    }

    /**
     * Indicate that the product has no stock left.
     */
    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'qty' => 0,
        ]);
    }
}
