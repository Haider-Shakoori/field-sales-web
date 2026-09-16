<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\Audit\AuditLogger;
use Database\Factories\CustomerLocationHistoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerLocationHistory extends Model
{
    /** @use HasFactory<CustomerLocationHistoryFactory> */
    use BelongsToTenant;

    use HasFactory;

    protected $table = 'customer_location_history';

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'latitude',
        'longitude',
        'address',
        'changed_by',
        'changed_at',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'changed_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        static::created(function (CustomerLocationHistory $history) {
            AuditLogger::log('customer.location_changed', $history, [], [
                'customer_id' => $history->customer_id,
                'latitude' => $history->latitude,
                'longitude' => $history->longitude,
                'address' => $history->address,
                'changed_by' => $history->changed_by,
            ]);
        });
    }
}
