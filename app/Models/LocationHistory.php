<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\LocationHistoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only GPS history. Rows are inserted in batches and never updated.
 */
class LocationHistory extends Model
{
    /** @use HasFactory<LocationHistoryFactory> */
    use BelongsToTenant;

    use HasFactory;

    protected $table = 'location_history';

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'salesman_id',
        'device_id',
        'client_uuid',
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
        'sync_batch_id',
        'sequence_number',
        'metadata',
        'created_at',
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
        'sequence_number' => 'integer',
        'metadata' => 'array',
        'created_at' => 'datetime',
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

    public function syncBatch(): BelongsTo
    {
        return $this->belongsTo(LocationSyncBatch::class, 'sync_batch_id');
    }
}
