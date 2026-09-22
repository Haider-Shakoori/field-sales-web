<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DailyBeatPlan;
use App\Models\DailyBeatPlanStop;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BeatPlanController extends Controller
{
    public function today(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('customers:view'), 403);

        $user = $request->user()->loadMissing(['tenant', 'salesman']);
        abort_unless($user->salesman?->is_active, 403);

        $date = now($user->tenant->timezone)->toDateString();

        $plan = DailyBeatPlan::with([
            'route',
            'stops.customer',
            'stops.completedVisit',
        ])
            ->where('salesman_id', $user->salesman->id)
            ->whereDate('plan_date', $date)
            ->first();

        return ApiResponse::success([
            'plan' => $plan ? $this->payload($plan) : null,
        ]);
    }

    private function payload(DailyBeatPlan $plan): array
    {
        return [
            'id' => $plan->uuid,
            'date' => $plan->plan_date?->toDateString(),
            'status' => $plan->status,
            'source_type' => $plan->source_type,
            'route_id' => $plan->route?->uuid,
            'route_name' => $plan->route?->name,
            'algorithm_version' => $plan->algorithm_version,
            'generated_at' => $plan->generated_at?->toISOString(),
            'summary' => [
                'total_stops' => $plan->total_stops,
                'planned_visit_minutes' => $plan->planned_visit_minutes,
                'estimated_distance_m' => $plan->estimated_distance_m,
                'missing_coordinates' => $plan->missing_coordinates,
            ],
            'warnings' => $plan->warnings ?? [],
            'stops' => $plan->stops
                ->map(fn (DailyBeatPlanStop $stop): array => [
                    'id' => $stop->uuid,
                    'sequence' => $stop->sequence_number,
                    'priority_tier' => $stop->priority_tier,
                    'priority_score' => $stop->priority_score,
                    'reason_codes' => $stop->reason_codes ?? [],
                    'signals' => $stop->signals ?? [],
                    'planned_visit_minutes' => $stop->planned_visit_minutes,
                    'estimated_distance_from_previous_m' => $stop->estimated_distance_from_previous_m,
                    'completed' => $stop->completed_visit_id !== null,
                    'completed_at' => $stop->completed_at?->toISOString(),
                    'visit_id' => $stop->completedVisit?->uuid,
                    'customer' => [
                        'id' => $stop->customer?->uuid,
                        'code' => $stop->customer?->code,
                        'name' => $stop->customer?->name,
                        'phone' => $stop->customer?->phone,
                        'address' => $stop->customer?->address,
                        'latitude' => $stop->customer?->latitude === null
                            ? null
                            : (float) $stop->customer->latitude,
                        'longitude' => $stop->customer?->longitude === null
                            ? null
                            : (float) $stop->customer->longitude,
                    ],
                ])
                ->values()
                ->all(),
        ];
    }
}
