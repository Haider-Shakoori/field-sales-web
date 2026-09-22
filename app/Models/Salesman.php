<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Salesman extends Model
{
    use BelongsToTenant, HasUuid;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(SalesmanAssignment::class);
    }

    public function collections(): HasMany
    {
        return $this->hasMany(Collection::class)->latest('collected_at');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class)->latest('spent_at');
    }

    public function targets(): HasMany
    {
        return $this->hasMany(SalesTarget::class)->latest('period_start');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class)->latest('ordered_at');
    }

    public function visits(): HasMany
    {
        return $this->hasMany(CustomerVisit::class);
    }

    public function dailyBeatPlans(): HasMany
    {
        return $this->hasMany(DailyBeatPlan::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.($this->last_name ?? ''));
    }
}
