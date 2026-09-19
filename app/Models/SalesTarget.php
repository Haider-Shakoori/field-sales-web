<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesTarget extends Model
{
    use BelongsToTenant, HasUuid;

    public const TYPES = [
        'sales_amount',
        'collections_amount',
        'orders_count',
        'visits_count',
    ];

    public const AMOUNT_TYPES = [
        'sales_amount',
        'collections_amount',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'target_value' => 'decimal:4',
            'period_start' => 'date',
            'period_end' => 'date',
        ];
    }

    public function salesman(): BelongsTo
    {
        return $this->belongsTo(Salesman::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function requiresCurrency(): bool
    {
        return in_array($this->target_type, self::AMOUNT_TYPES, true);
    }
}
