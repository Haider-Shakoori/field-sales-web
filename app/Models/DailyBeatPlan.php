<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DailyBeatPlan extends Model
{
    use BelongsToTenant, HasUuid;

    public const STATUS_PUBLISHED = 'published';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'plan_date' => 'date',
            'warnings' => 'array',
            'generated_at' => 'datetime',
            'total_stops' => 'integer',
            'planned_visit_minutes' => 'integer',
            'estimated_distance_m' => 'integer',
            'missing_coordinates' => 'integer',
        ];
    }

    public function salesman(): BelongsTo
    {
        return $this->belongsTo(Salesman::class);
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(SalesmanAssignment::class, 'assignment_id');
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(SalesRoute::class, 'route_id');
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function stops(): HasMany
    {
        return $this->hasMany(DailyBeatPlanStop::class, 'beat_plan_id')
            ->orderBy('sequence_number');
    }
}
