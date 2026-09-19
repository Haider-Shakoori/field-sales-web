<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use BelongsToTenant, HasUuid;

    public const CATEGORIES = [
        'fuel',
        'transport',
        'meals',
        'accommodation',
        'parking_tolls',
        'mobile_data',
        'office_supplies',
        'customer_entertainment',
        'other',
    ];

    public const STATUSES = ['pending', 'approved', 'rejected', 'cancelled'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'spent_at' => 'datetime',
            'amount' => 'decimal:4',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'accuracy' => 'decimal:2',
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function salesman(): BelongsTo
    {
        return $this->belongsTo(Salesman::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
