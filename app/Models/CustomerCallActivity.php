<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerCallActivity extends Model
{
    use BelongsToTenant, HasUuid;

    public const OUTCOMES = [
        'answered',
        'no_answer',
        'busy',
        'call_back_later',
        'order_discussion',
        'payment_follow_up',
        'other',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'called_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
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
}
