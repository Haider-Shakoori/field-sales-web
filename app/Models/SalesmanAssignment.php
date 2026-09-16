<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\Audit\AuditLogger;
use Database\Factories\SalesmanAssignmentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesmanAssignment extends Model
{
    /** @use HasFactory<SalesmanAssignmentFactory> */
    use BelongsToTenant;

    use HasFactory;
    use HasUuids;

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected $fillable = [
        'tenant_id',
        'salesman_id',
        'branch_id',
        'territory_id',
        'route_id',
        'supervisor_id',
        'effective_from',
        'effective_to',
        'created_by',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function salesman(): BelongsTo
    {
        return $this->belongsTo(Salesman::class, 'salesman_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function territory(): BelongsTo
    {
        return $this->belongsTo(Territory::class, 'territory_id');
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class, 'route_id');
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(Supervisor::class, 'supervisor_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isCurrentlyActive(): bool
    {
        return $this->effective_to === null || $this->effective_to >= now()->toDateString();
    }

    /**
     * Scope to assignments that are in effect today.
     */
    public function scopeActive($query)
    {
        return $query
            ->where(function ($q) {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', now()->startOfDay());
            })
            ->where('effective_from', '<=', now()->endOfDay());
    }

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        static::created(function (SalesmanAssignment $assignment) {
            AuditLogger::log('salesman.assignment_created', $assignment, [], [
                'salesman_id' => $assignment->salesman_id,
                'branch_id' => $assignment->branch_id,
                'territory_id' => $assignment->territory_id,
                'route_id' => $assignment->route_id,
                'supervisor_id' => $assignment->supervisor_id,
                'effective_from' => $assignment->effective_from?->toDateString(),
                'effective_to' => $assignment->effective_to?->toDateString(),
            ]);
        });

        static::updated(function (SalesmanAssignment $assignment) {
            AuditLogger::changed('salesman.assignment_updated', $assignment, $assignment->getDirty());
        });

        static::deleted(function (SalesmanAssignment $assignment) {
            AuditLogger::log('salesman.assignment_ended', $assignment, [], [
                'salesman_id' => $assignment->salesman_id,
                'effective_to' => $assignment->effective_to?->toDateString(),
            ]);
        });
    }
}
