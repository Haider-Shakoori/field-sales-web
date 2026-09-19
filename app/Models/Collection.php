<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Collection extends Model
{
    use BelongsToTenant, HasUuid;

    public const PAYMENT_METHODS = [
        'cash',
        'bank_transfer',
        'cheque',
        'card',
        'mobile_money',
        'other',
    ];

    public const STATUSES = ['pending', 'verified', 'rejected', 'cancelled'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'collected_at' => 'datetime',
            'amount' => 'decimal:4',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'accuracy' => 'decimal:2',
            'distance_meters' => 'decimal:2',
            'within_geofence' => 'boolean',
            'balance_before' => 'decimal:4',
            'overpayment_flag' => 'boolean',
            'status_changed_at' => 'datetime',
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

    public function visit(): BelongsTo
    {
        return $this->belongsTo(CustomerVisit::class);
    }

    public function statusChanger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'status_changed_by');
    }
}
