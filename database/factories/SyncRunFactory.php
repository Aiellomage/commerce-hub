<?php

namespace Database\Factories;

use App\Enums\SyncRunStatus;
use App\Enums\SyncRunType;
use App\Models\Store;
use App\Models\SyncRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SyncRun>
 */
class SyncRunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startedAt = fake()->dateTimeBetween('-1 week');
        $itemsTotal = fake()->numberBetween(10, 1000);

        return [
            'store_id' => Store::factory(),
            'type' => fake()->randomElement(SyncRunType::cases()),
            'status' => SyncRunStatus::Completed,
            'started_at' => $startedAt,
            'finished_at' => (clone $startedAt)->modify('+'.fake()->numberBetween(5, 600).' seconds'),
            'items_total' => $itemsTotal,
            'items_processed' => $itemsTotal,
            'items_failed' => 0,
        ];
    }

    /**
     * Indicate that the run has not started yet.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SyncRunStatus::Pending,
            'started_at' => null,
            'finished_at' => null,
            'items_processed' => 0,
        ]);
    }

    /**
     * Indicate that the run is still in progress.
     */
    public function running(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SyncRunStatus::Running,
            'finished_at' => null,
            'items_processed' => intdiv($attributes['items_total'], 2),
        ]);
    }

    /**
     * Indicate that the run stopped because of an error.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SyncRunStatus::Failed,
            'items_failed' => fake()->numberBetween(1, $attributes['items_total']),
            'error_message' => 'Magento API responded with 503 Service Unavailable',
        ]);
    }
}
