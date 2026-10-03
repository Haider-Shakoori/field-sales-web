<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Device extends Model
{
    use BelongsToTenant, HasUuid;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'registered_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'revoked_at' => 'datetime',
            'battery_level' => 'integer',
            'is_charging' => 'boolean',
            'power_save_mode' => 'boolean',
            'battery_optimization_exempt' => 'boolean',
            'background_restricted' => 'boolean',
            'location_services_enabled' => 'boolean',
            'background_tracking_active' => 'boolean',
            'workday_active' => 'boolean',
            'pending_sync_count' => 'integer',
            'failed_sync_count' => 'integer',
            'blocked_sync_count' => 'integer',
            'last_sync_at' => 'datetime',
            'is_physical_device' => 'boolean',
            'root_signal_detected' => 'boolean',
            'mock_location_detected' => 'boolean',
            'last_gps_fix_at' => 'datetime',
            'health_issues' => 'array',
            'health_reported_at' => 'datetime',
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

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function diagnostics(): HasMany
    {
        return $this->hasMany(MobileDiagnostic::class);
    }

    public function isRevoked(): bool
    {
        return ! $this->is_active || $this->revoked_at !== null;
    }

    public function effectiveHealthStatus(): string
    {
        if ($this->isRevoked()) {
            return 'revoked';
        }

        if (! $this->health_reported_at) {
            return 'unknown';
        }

        if ($this->health_reported_at->lt(now()->subMinutes(15))) {
            return 'stale';
        }

        return $this->health_status ?: 'unknown';
    }
}
