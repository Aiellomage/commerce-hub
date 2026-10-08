<?php

namespace App\Models;

use App\Enums\SyncRunStatus;
use App\Enums\SyncRunType;
use Database\Factories\SyncRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'store_id',
    'type',
    'status',
    'started_at',
    'finished_at',
    'items_total',
    'items_processed',
    'items_failed',
    'error_message',
])]
class SyncRun extends Model
{
    /** @use HasFactory<SyncRunFactory> */
    use HasFactory;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => SyncRunStatus::Pending,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => SyncRunType::class,
            'status' => SyncRunStatus::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'items_total' => 'integer',
            'items_processed' => 'integer',
            'items_failed' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Mark the run as started.
     */
    public function markRunning(): void
    {
        $this->update([
            'status' => SyncRunStatus::Running,
            'started_at' => now(),
        ]);
    }

    /**
     * Mark the run as successfully completed.
     */
    public function markCompleted(): void
    {
        $this->update([
            'status' => SyncRunStatus::Completed,
            'finished_at' => now(),
        ]);
    }

    /**
     * Mark the run as failed with the reason.
     */
    public function markFailed(string $errorMessage): void
    {
        $this->update([
            'status' => SyncRunStatus::Failed,
            'finished_at' => now(),
            'error_message' => $errorMessage,
        ]);
    }
}
