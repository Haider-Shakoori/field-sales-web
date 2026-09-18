<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesRoute extends Model
{
    use BelongsToTenant, HasUuid;

    protected $table = 'routes';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'weekdays' => 'array',
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

    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class, 'route_customers', 'route_id', 'customer_id')
            ->withPivot(['uuid', 'tenant_id', 'sequence_number', 'planned_visit_minutes', 'notes'])
            ->withTimestamps()
            ->orderByPivot('sequence_number');
    }

    public function customerMemberships(): HasMany
    {
        return $this->hasMany(RouteCustomer::class, 'route_id')
            ->orderBy('sequence_number');
    }

    public function salesmanAssignments(): HasMany
    {
        return $this->hasMany(SalesmanAssignment::class, 'route_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForWeekday(Builder $query, string $weekday): Builder
    {
        return $query->whereJsonContains('weekdays', strtolower($weekday));
    }
}
