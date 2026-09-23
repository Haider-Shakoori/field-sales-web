<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesmanStockMovement extends Model
{
    use BelongsToTenant, HasUuid;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'sellable_delta' => 'decimal:4',
            'reserved_delta' => 'decimal:4',
            'damaged_delta' => 'decimal:4',
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

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customerReturn(): BelongsTo
    {
        return $this->belongsTo(CustomerReturn::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
