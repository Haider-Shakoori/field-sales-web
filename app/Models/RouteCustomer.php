<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\Audit\AuditLogger;
use Database\Factories\RouteCustomerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RouteCustomer extends Model
{
    /** @use HasFactory<RouteCustomerFactory> */
    use BelongsToTenant;

    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'route_id',
        'customer_id',
        'visit_order',
        'effective_from',
        'effective_to',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class, 'route_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function isCurrentlyActive(): bool
    {
        return $this->effective_to === null || $this->effective_to >= now()->toDateString();
    }

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        static::created(function (RouteCustomer $routeCustomer) {
            AuditLogger::log('route.customer_assigned', $routeCustomer, [], [
                'route_id' => $routeCustomer->route_id,
                'customer_id' => $routeCustomer->customer_id,
                'visit_order' => $routeCustomer->visit_order,
                'effective_from' => $routeCustomer->effective_from?->toDateString(),
                'effective_to' => $routeCustomer->effective_to?->toDateString(),
            ]);
        });

        static::updated(function (RouteCustomer $routeCustomer) {
            AuditLogger::changed('route.customer_reassigned', $routeCustomer, $routeCustomer->getDirty());
        });

        static::deleted(function (RouteCustomer $routeCustomer) {
            AuditLogger::log('route.customer_removed', $routeCustomer, [], [
                'route_id' => $routeCustomer->route_id,
                'customer_id' => $routeCustomer->customer_id,
            ]);
        });
    }
}
