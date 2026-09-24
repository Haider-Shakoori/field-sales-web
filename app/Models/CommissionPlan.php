<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommissionPlan extends Model
{
    use BelongsToTenant, HasUuid;

    public const METRICS = ['sales', 'collections'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['rate_percent'=>'decimal:4','minimum_source_amount'=>'decimal:4','effective_from'=>'date','effective_to'=>'date','is_active'=>'boolean'];
    }

    public function salesmen(): BelongsToMany
    {
        return $this->belongsToMany(Salesman::class, 'commission_plan_salesmen')->withPivot('tenant_id')->withTimestamps();
    }

    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function territory(): BelongsTo { return $this->belongsTo(Territory::class); }
    public function earnings(): HasMany { return $this->hasMany(CommissionEarning::class); }
}