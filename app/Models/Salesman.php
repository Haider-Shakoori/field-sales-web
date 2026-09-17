<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\SalesmanFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Salesman extends Model
{
    /** @use HasFactory<SalesmanFactory> */
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
        'user_id',
        'employee_code',
        'first_name',
        'last_name',
        'phone',
        'email',
        'hire_date',
        'designation',
        'is_active',
    ];

    protected $casts = [
        'hire_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * Devices registered to this salesman.
     */
    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(SalesmanAssignment::class)->orderByDesc('effective_from');
    }

    public function currentAssignment()
    {
        return $this->hasOne(SalesmanAssignment::class)
            ->where(function ($query) {
                $query->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', now()->startOfDay());
            })
            ->where('effective_from', '<=', now()->endOfDay())
            ->latest('effective_from');
    }

    public function currentTerritory()
    {
        return $this->currentAssignment()->with('territory')->first()?->territory;
    }

    public function currentRoute()
    {
        return $this->currentAssignment()->with('route')->first()?->route;
    }

    public function currentSupervisor()
    {
        return $this->currentAssignment()->with('supervisor')->first()?->supervisor;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
