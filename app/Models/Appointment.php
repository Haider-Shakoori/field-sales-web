<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appointment extends Model
{
    use BelongsToTenant, HasUuid;

    public const TYPES = [
        'meeting',
        'visit',
        'call',
        'collection',
        'order',
        'other',
    ];

    public const STATUSES = [
        'scheduled',
        'completed',
        'cancelled',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
            'completed_at' => 'datetime',
            'reminder_minutes_before' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function assignedSalesman(): BelongsTo
    {
        return $this->belongsTo(Salesman::class, 'assigned_salesman_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
