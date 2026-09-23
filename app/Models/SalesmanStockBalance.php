<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesmanStockBalance extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'sellable_quantity' => 'decimal:4',
            'reserved_quantity' => 'decimal:4',
            'damaged_quantity' => 'decimal:4',
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

    public function getAvailableQuantityAttribute(): float
    {
        return round(
            (float) $this->sellable_quantity - (float) $this->reserved_quantity,
            4,
        );
    }
}
