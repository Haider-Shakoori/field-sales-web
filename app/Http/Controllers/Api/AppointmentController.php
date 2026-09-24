<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Customer;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AppointmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('appointments:view'), 403);

        $user = $request->user()->load(['tenant', 'salesman']);
        abort_unless($user->salesman?->is_active, 403);

        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'status' => ['nullable', Rule::in(Appointment::STATUSES)],
        ]);

        $timezone = $user->tenant?->timezone ?: config('app.timezone', 'UTC');
        $from = isset($validated['from'])
            ? CarbonImmutable::parse($validated['from'], $timezone)->startOfDay()
            : CarbonImmutable::now($timezone)->subDays(7)->startOfDay();
        $to = isset($validated['to'])
            ? CarbonImmutable::parse($validated['to'], $timezone)->endOfDay()
            : CarbonImmutable::now($timezone)->addDays(30)->endOfDay();

        if ($from->diffInDays($to) > 366) {
            return ApiResponse::error(
                'Appointment range cannot exceed 366 days.',
                422,
                null,
                'VALIDATION_ERROR',
            );
        }

        $appointments = Appointment::query()
            ->with(['customer', 'assignedSalesman'])
            ->where('assigned_salesman_id', $user->salesman->id)
            ->whereBetween('starts_at', [$from->utc(), $to->utc()])
            ->when(
                ! empty($validated['status']),
                fn ($query) => $query->where('status', $validated['status']),
            )
            ->orderBy('starts_at')
            ->get()
            ->map(fn (Appointment $appointment) => $this->payload($appointment))
            ->values()
            ->all();

        return ApiResponse::success($appointments);
    }

    public function store(
        Request $request,
        AuditLogger $audit,
    ): JsonResponse {
        abort_unless($request->user()->hasPermission('appointments:manage'), 403);

        $user = $request->user()->load(['tenant', 'salesman']);
        abort_unless($user->salesman?->is_active, 403);

        $validated = $request->validate([
            'offline_uuid' => ['required', 'uuid'],
            'customer_id' => ['nullable', 'uuid'],
            'title' => ['required', 'string', 'max:160'],
            'type' => ['required', Rule::in(Appointment::TYPES)],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'reminder_minutes_before' => [
                'nullable',
                'integer',
                Rule::in([0, 15, 30, 60, 120, 1440]),
            ],
            'location' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $existing = Appointment::where('uuid', $validated['offline_uuid'])->first();

        if ($existing) {
            abort_unless(
                (int) $existing->assigned_salesman_id === (int) $user->salesman->id,
                409,
            );

            return ApiResponse::success($this->payload($existing->load('customer')));
        }

        $customer = isset($validated['customer_id'])
            ? Customer::where('uuid', $validated['customer_id'])->active()->firstOrFail()
            : null;
        $startsAt = CarbonImmutable::parse($validated['starts_at'])->utc();
        $endsAt = isset($validated['ends_at'])
            ? CarbonImmutable::parse($validated['ends_at'])->utc()
            : null;

        $appointment = Appointment::create([
            'uuid' => $validated['offline_uuid'],
            'customer_id' => $customer?->id,
            'assigned_salesman_id' => $user->salesman->id,
            'created_by' => $user->id,
            'title' => trim($validated['title']),
            'type' => $validated['type'],
            'status' => 'scheduled',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'reminder_minutes_before' => $validated['reminder_minutes_before'] ?? null,
            'location' => $validated['location'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        $audit->record('appointment.created_mobile', $appointment, [], [
            'customer_id' => $appointment->customer_id,
            'assigned_salesman_id' => $appointment->assigned_salesman_id,
            'starts_at' => $appointment->starts_at?->toISOString(),
        ]);

        return ApiResponse::success(
            $this->payload($appointment->load('customer')),
            201,
        );
    }

    public function updateStatus(
        Request $request,
        Appointment $appointment,
        AuditLogger $audit,
    ): JsonResponse {
        abort_unless($request->user()->hasPermission('appointments:manage'), 403);

        $user = $request->user()->load('salesman');
        abort_unless(
            $user->salesman?->is_active
                && (int) $appointment->assigned_salesman_id
                    === (int) $user->salesman->id,
            404,
        );

        $validated = $request->validate([
            'status' => ['required', Rule::in(Appointment::STATUSES)],
        ]);
        $before = [
            'status' => $appointment->status,
            'completed_at' => $appointment->completed_at?->toISOString(),
        ];
        $completed = $validated['status'] === 'completed';

        $appointment->update([
            'status' => $validated['status'],
            'completed_at' => $completed ? now() : null,
            'completed_by' => $completed ? $user->id : null,
        ]);

        $audit->record('appointment.status_changed_mobile', $appointment, $before, [
            'status' => $appointment->status,
            'completed_at' => $appointment->completed_at?->toISOString(),
        ]);

        return ApiResponse::success(
            $this->payload($appointment->fresh()->load('customer')),
        );
    }

    private function payload(Appointment $appointment): array
    {
        return [
            'id' => $appointment->uuid,
            'customer_id' => $appointment->customer?->uuid,
            'customer_name' => $appointment->customer?->name,
            'title' => $appointment->title,
            'type' => $appointment->type,
            'status' => $appointment->status,
            'starts_at' => $appointment->starts_at?->toISOString(),
            'ends_at' => $appointment->ends_at?->toISOString(),
            'reminder_minutes_before' => $appointment->reminder_minutes_before,
            'location' => $appointment->location,
            'notes' => $appointment->notes,
            'completed_at' => $appointment->completed_at?->toISOString(),
            'updated_at' => $appointment->updated_at?->toISOString(),
        ];
    }
}
