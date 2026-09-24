<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Customer;
use App\Services\AppointmentAccessService;
use App\Services\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AppointmentController extends Controller
{
    public function index(
        Request $request,
        AppointmentAccessService $access,
    ): View {
        abort_unless($request->user()->hasPermission('appointments:view'), 403);

        $user = $request->user()->loadMissing('tenant');
        $timezone = $user->tenant?->timezone ?: config('app.timezone', 'UTC');
        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'salesman' => ['nullable', 'uuid'],
            'status' => ['nullable', Rule::in(Appointment::STATUSES)],
        ]);

        $month = isset($validated['month'])
            ? CarbonImmutable::createFromFormat('Y-m', $validated['month'], $timezone)
            : CarbonImmutable::now($timezone);
        $month = $month->startOfMonth();
        $gridStart = $month->startOfWeek();
        $gridEnd = $month->endOfMonth()->endOfWeek();

        $salesmen = $access->assignableSalesmen($user);
        $selectedSalesman = isset($validated['salesman'])
            ? $salesmen->firstWhere('uuid', $validated['salesman'])
            : null;

        $appointments = $access->scopedQuery($user)
            ->with(['customer', 'assignedSalesman.user'])
            ->whereBetween('starts_at', [
                $gridStart->utc(),
                $gridEnd->endOfDay()->utc(),
            ])
            ->when(
                $selectedSalesman,
                fn ($query) => $query->where(
                    'assigned_salesman_id',
                    $selectedSalesman->id,
                ),
            )
            ->when(
                ! empty($validated['status']),
                fn ($query) => $query->where('status', $validated['status']),
            )
            ->orderBy('starts_at')
            ->get();

        $grouped = $appointments->groupBy(
            fn (Appointment $appointment) => $appointment->starts_at
                ->copy()
                ->setTimezone($timezone)
                ->toDateString(),
        );

        return view('admin.appointments.index', [
            'appointments' => $appointments,
            'groupedAppointments' => $grouped,
            'salesmen' => $salesmen,
            'customers' => Customer::active()->orderBy('name')->get(),
            'month' => $month,
            'gridStart' => $gridStart,
            'gridEnd' => $gridEnd,
            'timezone' => $timezone,
            'filters' => [
                'salesman' => $selectedSalesman?->uuid,
                'status' => $validated['status'] ?? '',
            ],
            'canManage' => $user->hasPermission('appointments:manage'),
        ]);
    }

    public function store(
        Request $request,
        AppointmentAccessService $access,
        AuditLogger $audit,
    ): RedirectResponse {
        abort_unless($request->user()->hasPermission('appointments:manage'), 403);

        $user = $request->user()->loadMissing(['tenant', 'salesman']);
        $validated = $this->validated($request);
        $salesmanId = $this->salesmanId($user, $validated);

        abort_unless($access->canUseSalesman($user, $salesmanId), 403);

        $appointment = Appointment::create([
            ...$this->attributes($user, $validated, $salesmanId),
            'created_by' => $user->id,
            'status' => 'scheduled',
        ]);

        $audit->record('appointment.created', $appointment, [], [
            'customer_id' => $appointment->customer_id,
            'assigned_salesman_id' => $appointment->assigned_salesman_id,
            'starts_at' => $appointment->starts_at?->toISOString(),
            'type' => $appointment->type,
        ]);

        return back()->with('status', __('Appointment scheduled.'));
    }

    public function update(
        Request $request,
        Appointment $appointment,
        AppointmentAccessService $access,
        AuditLogger $audit,
    ): RedirectResponse {
        abort_unless($access->canManage($request->user(), $appointment), 403);

        $user = $request->user()->loadMissing(['tenant', 'salesman']);
        $validated = $this->validated($request);
        $salesmanId = $this->salesmanId($user, $validated);

        abort_unless($access->canUseSalesman($user, $salesmanId), 403);

        $before = $appointment->only([
            'customer_id',
            'assigned_salesman_id',
            'title',
            'type',
            'starts_at',
            'ends_at',
            'reminder_minutes_before',
            'location',
            'notes',
        ]);

        $appointment->update([
            ...$this->attributes($user, $validated, $salesmanId),
            'reminder_sent_at' => null,
        ]);

        $audit->record('appointment.updated', $appointment, $before, [
            'customer_id' => $appointment->customer_id,
            'assigned_salesman_id' => $appointment->assigned_salesman_id,
            'starts_at' => $appointment->starts_at?->toISOString(),
            'type' => $appointment->type,
        ]);

        return back()->with('status', __('Appointment updated.'));
    }

    public function updateStatus(
        Request $request,
        Appointment $appointment,
        AppointmentAccessService $access,
        AuditLogger $audit,
    ): RedirectResponse {
        abort_unless($access->canManage($request->user(), $appointment), 403);

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
            'completed_by' => $completed ? $request->user()->id : null,
        ]);

        $audit->record('appointment.status_changed', $appointment, $before, [
            'status' => $appointment->status,
            'completed_at' => $appointment->completed_at?->toISOString(),
        ]);

        return back()->with('status', __('Appointment status updated.'));
    }

    private function validated(Request $request): array
    {
        $tenantId = $request->user()->tenant_id;

        return $request->validate([
            'customer_id' => [
                'nullable',
                'integer',
                Rule::exists('customers', 'id')->where('tenant_id', $tenantId),
            ],
            'assigned_salesman_id' => [
                'nullable',
                'integer',
                Rule::exists('salesmen', 'id')->where('tenant_id', $tenantId),
            ],
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
    }

    private function salesmanId($user, array $validated): ?int
    {
        if ($user->salesman?->is_active) {
            return (int) $user->salesman->id;
        }

        return isset($validated['assigned_salesman_id'])
            ? (int) $validated['assigned_salesman_id']
            : null;
    }

    private function attributes($user, array $validated, ?int $salesmanId): array
    {
        $timezone = $user->tenant?->timezone ?: config('app.timezone', 'UTC');

        return [
            'customer_id' => $validated['customer_id'] ?? null,
            'assigned_salesman_id' => $salesmanId,
            'title' => trim($validated['title']),
            'type' => $validated['type'],
            'starts_at' => CarbonImmutable::parse(
                $validated['starts_at'],
                $timezone,
            )->utc(),
            'ends_at' => isset($validated['ends_at'])
                ? CarbonImmutable::parse($validated['ends_at'], $timezone)->utc()
                : null,
            'reminder_minutes_before' => $validated['reminder_minutes_before'] ?? null,
            'location' => $validated['location'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ];
    }
}
