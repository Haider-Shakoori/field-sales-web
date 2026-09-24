<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use BelongsToTenant, HasUuid;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'credit_limit' => 'decimal:4',
            'credit_terms_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function territory(): BelongsTo
    {
        return $this->belongsTo(Territory::class);
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function routes(): BelongsToMany
    {
        return $this->belongsToMany(
            SalesRoute::class,
            'route_customers',
            'customer_id',
            'route_id'
        )
            ->withPivot([
                'uuid',
                'tenant_id',
                'sequence_number',
                'planned_visit_minutes',
                'notes',
            ])
            ->withTimestamps();
    }

    public function routeMemberships(): HasMany
    {
        return $this->hasMany(RouteCustomer::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(CustomerVisit::class);
    }

    public function collections(): HasMany
    {
        return $this->hasMany(Collection::class)->latest('collected_at');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class)->latest('ordered_at');
    }

    public function sourceLeads(): HasMany
    {
        return $this->hasMany(Lead::class, 'converted_customer_id');
    }

    public function sourceLeads(): HasMany
    {
        return $this->hasMany(Lead::class, 'converted_customer_id');
    }

    public function callActivities(): HasMany
    {
        return $this->hasMany(CustomerCallActivity::class)->latest('called_at');
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(CustomerFollowUp::class)->latest('due_at');
    }

    public function communicationDeliveries(): HasMany
    {
        return $this->hasMany(CustomerCommunicationDelivery::class)->latest();
    }

    public function portalAccesses(): HasMany
    {
        return $this->hasMany(CustomerPortalAccess::class)->latest();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}