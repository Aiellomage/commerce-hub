<?php

namespace Database\Factories;

use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Store>
 */
class StoreFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'code' => fake()->unique()->lexify('store_????'),
            'magento_url' => fake()->url(),
            'magento_credentials' => [
                'consumer_key' => Str::random(32),
                'consumer_secret' => Str::random(32),
                'access_token' => Str::random(32),
                'access_token_secret' => Str::random(32),
            ],
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the store is disabled and must not be synced.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
