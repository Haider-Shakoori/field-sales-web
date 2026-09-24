<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommissionEarning extends Model
{
    use BelongsToTenant, HasUuid;

    protected $guarded = [];
    protected function casts(): array { return ['source_amount'=>'decimal:4','rate_percent'=>'decimal:4','commission_amount'=>'decimal:4','earned_at'=>'datetime','paid_at'=>'datetime']; }
    public function plan(): BelongsTo { return $this->belongsTo(CommissionPlan::class, 'commission_plan_id'); }
    public function salesman(): BelongsTo { return $this->belongsTo(Salesman::class); }
}
