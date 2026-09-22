<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyBeatPlanStop extends Model
{
    use BelongsToTenant, HasUuid;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'reason_codes' => 'array',
            'signals' => 'array',
            'estimated_distance_from_previous_m' => 'integer',
            'planned_visit_minutes' => 'integer',
            'priority_score' => 'integer',
            'source_route_sequence' => 'integer',
            'sequence_number' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(DailyBeatPlan::class, 'beat_plan_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function completedVisit(): BelongsTo
    {
        return $this->belongsTo(CustomerVisit::class, 'completed_visit_id');
    }
}
