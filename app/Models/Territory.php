<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\Audit\AuditLogger;
use Database\Factories\TerritoryFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Territory extends Model
{
    /** @use HasFactory<TerritoryFactory> */
    use BelongsToTenant;

    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'name',
        'code',
        'description',
        'latitude',
        'longitude',
        'radius_km',
        'is_active',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'radius_km' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function routes(): HasMany
    {
        return $this->hasMany(Route::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function salesmanAssignments(): HasMany
    {
        return $this->hasMany(SalesmanAssignment::class);
    }

    public function supervisorAssignments(): HasMany
    {
        return $this->hasMany(SupervisorAssignment::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForBranch($query, int $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        static::created(function (Territory $territory) {
            AuditLogger::log('territory.created', $territory, [], [
                'code' => $territory->code,
                'name' => $territory->name,
                'branch_id' => $territory->branch_id,
            ]);
        });

        static::updated(function (Territory $territory) {
            AuditLogger::changed('territory.updated', $territory, $territory->getDirty());
        });

        static::deleted(function (Territory $territory) {
            AuditLogger::log('territory.deactivated', $territory, [], [
                'code' => $territory->code,
                'name' => $territory->name,
            ]);
        });
    }
}
