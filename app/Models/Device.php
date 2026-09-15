<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\DeviceFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Device extends Model
{
    /** @use HasFactory<DeviceFactory> */
    use BelongsToTenant;

    use HasFactory;
    use HasUuids;

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected $fillable = [
        'tenant_id',
        'user_id',
        'salesman_id',
        'device_uuid',
        'installation_uuid',
        'device_model',
        'manufacturer',
        'android_version',
        'app_version',
        'push_token',
        'fcm_token',
        'is_active',
        'registered_at',
        'last_seen_at',
        'revoked_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'registered_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function salesman(): BelongsTo
    {
        return $this->belongsTo(Salesman::class, 'salesman_id');
    }

    /**
     * Check if the device is active (not revoked).
     */
    public function isActive(): bool
    {
        return $this->is_active && $this->revoked_at === null;
    }

    /**
     * Revoke this device.
     */
    public function revoke(): void
    {
        $this->update([
            'is_active' => false,
            'revoked_at' => now('UTC'),
        ]);
    }

    /**
     * Scope to only active (non-revoked) devices.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->whereNull('revoked_at');
    }

    /**
     * Scope to only revoked devices.
     */
    public function scopeRevoked($query)
    {
        return $query->where('is_active', false);
    }
}
