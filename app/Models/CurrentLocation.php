<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\CurrentLocationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CurrentLocation extends Model
{
    /** @use HasFactory<CurrentLocationFactory> */
    use BelongsToTenant;

    use HasFactory;

    /**
     * The table only tracks the latest update timestamp.
     */
    public const CREATED_AT = null;

    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'salesman_id',
        'device_id',
        'latitude',
        'longitude',
        'horizontal_accuracy',
        'altitude',
        'speed',
        'heading',
        'battery_level',
        'is_charging',
        'network_status',
        'is_mock_location',
        'provider',
        'recorded_at',
        'received_at',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'horizontal_accuracy' => 'decimal:2',
        'altitude' => 'decimal:2',
        'speed' => 'decimal:2',
        'heading' => 'decimal:2',
        'battery_level' => 'integer',
        'is_charging' => 'boolean',
        'is_mock_location' => 'boolean',
        'recorded_at' => 'datetime',
        'received_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function salesman(): BelongsTo
    {
        return $this->belongsTo(Salesman::class, 'salesman_id');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id');
    }

    /**
     * A latest-location row is only stale after the configured tracking window.
     */
    public function scopeStale($query, int $minutes = 15)
    {
        return $query->where('recorded_at', '<', now('UTC')->subMinutes($minutes));
    }
}
