<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesmanStockMovement extends Model
{
    use BelongsToTenant, HasUuid;

    public const BUCKETS = ['sellable', 'damaged'];

    public const TYPES = [
        'issue',
        'sale',
        'order_cancel_restore',
        'customer_return',
        'warehouse_return',
        'adjustment',
        'damage_transfer',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'quantity_change' => 'decimal:4',
            'occurred_at' => 'datetime',
        ];
    }

    public function salesman(): BelongsTo
    {
        return $this->belongsTo(Salesman::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
