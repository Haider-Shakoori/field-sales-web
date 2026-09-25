<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommissionRule extends Model
{
    use BelongsToTenant, HasUuid;

    public const BASIS_TYPES = [
        'sales_amount',
        'collections_amount',
        'product_sales_amount',
        'target_achievement',
    ];

    public const REWARD_TYPES = ['percentage', 'fixed'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:4',
            'minimum_basis' => 'decimal:4',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function salesman(): BelongsTo
    {
        return $this->belongsTo(Salesman::class);
    }

    public function territory(): BelongsTo
    {
        return $this->belongsTo(Territory::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function runLines(): HasMany
    {
        return $this->hasMany(CommissionRunLine::class);
    }

    public function scopeEffectiveDuring(Builder $query, string $from, string $to): Builder
    {
        return $query->whereDate('effective_from', '<=', $to)
            ->where(function (Builder $window) use ($from): void {
                $window->whereNull('effective_to')->orWhereDate('effective_to', '>=', $from);
            });
    }
}
