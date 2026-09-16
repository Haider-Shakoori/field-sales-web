<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\Audit\AuditLogger;
use App\Support\FieldProfiles\EmployeeCodeGenerator;
use App\Support\Tenancy\TenantContext;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
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
        'code',
        'business_name',
        'contact_person',
        'phone',
        'whatsapp',
        'category_id',
        'province',
        'district',
        'address',
        'latitude',
        'longitude',
        'geofence_radius',
        'photo_url',
        'assigned_salesman_id',
        'territory_id',
        'route_id',
        'credit_limit',
        'outstanding_balance',
        'price_list_id',
        'visit_frequency',
        'is_active',
        'notes',
        'uuid',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'geofence_radius' => 'integer',
        'credit_limit' => 'decimal:2',
        'outstanding_balance' => 'decimal:2',
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

    public function category(): BelongsTo
    {
        return $this->belongsTo(CustomerCategory::class, 'category_id');
    }

    public function assignedSalesman(): BelongsTo
    {
        return $this->belongsTo(Salesman::class, 'assigned_salesman_id');
    }

    public function territory(): BelongsTo
    {
        return $this->belongsTo(Territory::class, 'territory_id');
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class, 'route_id');
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class, 'price_list_id');
    }

    public function routeCustomers(): HasMany
    {
        return $this->hasMany(RouteCustomer::class);
    }

    public function routes(): BelongsToMany
    {
        return $this->belongsToMany(Route::class, 'route_customers')
            ->withPivot('visit_order', 'effective_from', 'effective_to')
            ->orderBy('visit_order');
    }

    public function locationHistory(): HasMany
    {
        return $this->hasMany(CustomerLocationHistory::class)->orderByDesc('changed_at');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForSalesman($query, int $salesmanId)
    {
        return $query->where('assigned_salesman_id', $salesmanId);
    }

    public function scopeInTerritory($query, int $territoryId)
    {
        return $query->where('territory_id', $territoryId);
    }

    public function scopeInRoute($query, int $routeId)
    {
        return $query->where('route_id', $routeId);
    }

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        static::creating(function (Customer $customer) {
            $tenantId = TenantContext::currentId();
            if ($tenantId && empty($customer->code)) {
                $customer->code = EmployeeCodeGenerator::nextCustomerCode($tenantId);
            }
        });

        static::updating(function (Customer $customer) {
            // Track location changes
            if ($customer->isDirty('latitude') || $customer->isDirty('longitude') || $customer->isDirty('address')) {
                $oldLatitude = $customer->getOriginal('latitude');
                $oldLongitude = $customer->getOriginal('longitude');
                $oldAddress = $customer->getOriginal('address');

                // Only create history if location actually changed
                if ($oldLatitude !== null && $oldLongitude !== null &&
                    ($oldLatitude != $customer->latitude || $oldLongitude != $customer->longitude || $oldAddress != $customer->address)) {
                    $customer->locationHistory()->create([
                        'tenant_id' => $customer->tenant_id,
                        'latitude' => $oldLatitude,
                        'longitude' => $oldLongitude,
                        'address' => $oldAddress,
                        'changed_by' => auth()->id(),
                        'changed_at' => now(),
                    ]);
                }
            }
        });

        static::created(function (Customer $customer) {
            AuditLogger::log('customer.created', $customer, [], [
                'code' => $customer->code,
                'business_name' => $customer->business_name,
                'contact_person' => $customer->contact_person,
                'phone' => $customer->phone,
                'territory_id' => $customer->territory_id,
                'route_id' => $customer->route_id,
                'assigned_salesman_id' => $customer->assigned_salesman_id,
            ]);
        });

        static::updated(function (Customer $customer) {
            AuditLogger::changed('customer.updated', $customer, $customer->getDirty());

            if ($customer->wasChanged('price_list_id')) {
                AuditLogger::log('customer.price_list_changed', $customer, [
                    'price_list_id' => $customer->getOriginal('price_list_id'),
                ], [
                    'price_list_id' => $customer->price_list_id,
                    'code' => $customer->code,
                    'business_name' => $customer->business_name,
                ]);
            }
        });

        static::deleted(function (Customer $customer) {
            AuditLogger::log('customer.deactivated', $customer, [], [
                'code' => $customer->code,
                'business_name' => $customer->business_name,
            ]);
        });
    }
}
