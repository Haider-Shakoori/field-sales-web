<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\LocationSyncBatchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LocationSyncBatch extends Model
{
    /** @use HasFactory<LocationSyncBatchFactory> */
    use BelongsToTenant;

    use HasFactory;

    /**
     * The table only tracks creation; processing is recorded via processed_at.
     */
    public const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id',
        'device_id',
        'user_id',
        'batch_uuid',
        'point_count',
        'received_at',
        'processed_at',
    ];

    protected $casts = [
        'point_count' => 'integer',
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
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

    public function points(): HasMany
    {
        return $this->hasMany(LocationHistory::class, 'sync_batch_id');
    }
}
