<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\PrivacyAcknowledgementFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrivacyAcknowledgement extends Model
{
    /** @use HasFactory<PrivacyAcknowledgementFactory> */
    use BelongsToTenant;

    use HasFactory;
    use HasUuids;

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected $fillable = [
        'uuid',
        'tenant_id',
        'user_id',
        'device_id',
        'policy_version',
        'acknowledged_at',
        'app_version',
    ];

    protected $casts = [
        'acknowledged_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id');
    }
}
