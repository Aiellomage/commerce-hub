<?php

namespace Database\Factories;

use App\Enums\ErpStatus;
use App\Models\Order;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
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
            'increment_id' => fake()->unique()->numerify('000######'),
            'status' => fake()->randomElement(['pending', 'processing', 'complete']),
            'customer_email' => fake()->safeEmail(),
            'customer_name' => fake()->name(),
            'grand_total' => fake()->randomFloat(4, 10, 1000),
            'currency' => 'EUR',
            'placed_at' => fake()->dateTimeBetween('-1 month'),
            'erp_status' => ErpStatus::Pending,
        ];
    }

    /**
     * Indicate that the order has been accepted by the ERP.
     */
    public function exported(): static
    {
        return $this->state(fn (array $attributes) => [
            'erp_status' => ErpStatus::Exported,
            'erp_reference' => fake()->bothify('ERP-#####'),
            'exported_at' => now(),
        ]);
    }

    /**
     * Indicate that the ERP rejected the order.
     */
    public function exportFailed(): static
    {
        return $this->state(fn (array $attributes) => [
            'erp_status' => ErpStatus::Failed,
        ]);
    }
}
