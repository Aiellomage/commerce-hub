<?php

namespace App\Models;

use App\Enums\ErpStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'store_id',
    'magento_id',
    'increment_id',
    'status',
    'customer_email',
    'customer_name',
    'grand_total',
    'currency',
    'placed_at',
    'erp_status',
    'erp_reference',
    'exported_at',
])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'erp_status' => ErpStatus::Pending,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'magento_id' => 'integer',
            'grand_total' => 'decimal:4',
            'placed_at' => 'datetime',
            'erp_status' => ErpStatus::class,
            'exported_at' => 'datetime',
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
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Scope a query to only include orders still waiting to be sent to the ERP.
     *
     * @param  Builder<Order>  $query
     */
    #[Scope]
    protected function pendingExport(Builder $query): void
    {
        $query->where('erp_status', ErpStatus::Pending);
    }

    /**
     * Mark the order as accepted by the ERP.
     */
    public function markExported(string $erpReference): void
    {
        $this->update([
            'erp_status' => ErpStatus::Exported,
            'erp_reference' => $erpReference,
            'exported_at' => now(),
        ]);
    }

    /**
     * Mark the order as rejected by the ERP.
     */
    public function markExportFailed(): void
    {
        $this->update([
            'erp_status' => ErpStatus::Failed,
        ]);
    }
}
