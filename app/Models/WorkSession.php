<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\Audit\AuditLogger;
use Database\Factories\WorkSessionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkSession extends Model
{
    /** @use HasFactory<WorkSessionFactory> */
    use BelongsToTenant;

    use HasFactory;
    use HasUuids;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CORRECTED = 'corrected';

    public const STATUS_APPROVED = 'approved';

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected $fillable = [
        'uuid',
        'tenant_id',
        'user_id',
        'salesman_id',
        'device_id',
        'date',
        'start_time',
        'end_time',
        'start_latitude',
        'start_longitude',
        'end_latitude',
        'end_longitude',
        'status',
        'duration_minutes',
        'is_late_start',
        'is_early_finish',
        'notes',
        'corrected_by',
        'corrected_at',
        'correction_reason',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'date' => 'date',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'start_latitude' => 'decimal:7',
        'start_longitude' => 'decimal:7',
        'end_latitude' => 'decimal:7',
        'end_longitude' => 'decimal:7',
        'duration_minutes' => 'integer',
        'is_late_start' => 'boolean',
        'is_early_finish' => 'boolean',
        'corrected_at' => 'datetime',
        'approved_at' => 'datetime',
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

    public function correctedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrected_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE && $this->end_time === null;
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE)->whereNull('end_time');
    }

    public function scopeForDate($query, string $date)
    {
        return $query->whereDate('date', $date);
    }

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        static::created(function (WorkSession $session) {
            AuditLogger::log('work_session.started', $session, [], [
                'user_id' => $session->user_id,
                'salesman_id' => $session->salesman_id,
                'date' => $session->date?->toDateString(),
                'start_time' => $session->start_time?->toIso8601String(),
            ]);
        });

        static::updated(function (WorkSession $session) {
            AuditLogger::changed('work_session.updated', $session, $session->getDirty());
        });
    }
}
