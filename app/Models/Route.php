<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\Audit\AuditLogger;
use Database\Factories\RouteFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Route extends Model
{
    /** @use HasFactory<RouteFactory> */
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
        'territory_id',
        'name',
        'code',
        'description',
        'weekday',
        'is_active',
    ];

    protected $casts = [
        'weekday' => 'integer',
        'is_active' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function territory(): BelongsTo
    {
        return $this->belongsTo(Territory::class, 'territory_id');
    }

    public function routeCustomers(): HasMany
    {
        return $this->hasMany(RouteCustomer::class)->orderBy('visit_order');
    }

    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class, 'route_customers')
            ->withPivot('visit_order', 'effective_from', 'effective_to')
            ->orderBy('visit_order');
    }

    public function salesmanAssignments(): HasMany
    {
        return $this->hasMany(SalesmanAssignment::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForTerritory($query, int $territoryId)
    {
        return $query->where('territory_id', $territoryId);
    }

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        static::created(function (Route $route) {
            AuditLogger::log('route.created', $route, [], [
                'code' => $route->code,
                'name' => $route->name,
                'territory_id' => $route->territory_id,
                'weekday' => $route->weekday,
            ]);
        });

        static::updated(function (Route $route) {
            AuditLogger::changed('route.updated', $route, $route->getDirty());
        });

        static::deleted(function (Route $route) {
            AuditLogger::log('route.deactivated', $route, [], [
                'code' => $route->code,
                'name' => $route->name,
            ]);
        });
    }
}
