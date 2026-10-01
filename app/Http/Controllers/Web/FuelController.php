<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\Expense;
use App\Models\Salesman;
use App\Services\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FuelController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'salesman' => ['nullable', 'uuid'],
            'vehicle' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(Expense::STATUSES)],
        ]);

        $user = $request->user()->loadMissing('tenant');
        $timezone = $user->tenant?->timezone ?: config('app.timezone', 'UTC');
        $dateTo = $validated['date_to'] ?? CarbonImmutable::now($timezone)->toDateString();
        $dateFrom = $validated['date_from']
            ?? CarbonImmutable::parse($dateTo, $timezone)->subDays(29)->toDateString();

        $from = CarbonImmutable::parse($dateFrom, $timezone)->startOfDay()->utc();
        $to = CarbonImmutable::parse($dateTo, $timezone)->endOfDay()->utc();

        $salesmen = Salesman::query()->with('user.branch')->orderBy('employee_code')->get();
        $selectedSalesman = null;
        if (! empty($validated['salesman'])) {
            $selectedSalesman = $salesmen->firstWhere('uuid', $validated['salesman']);
            abort_unless($selectedSalesman, 404);
        }

        $vehicle = trim((string) ($validated['vehicle'] ?? ''));
        $status = $validated['status'] ?? null;

        $base = Expense::query()
            ->where('category', 'fuel')
            ->whereBetween('spent_at', [$from, $to])
            ->when($selectedSalesman, fn ($query) => $query->where('salesman_id', $selectedSalesman->id))
            ->when($vehicle !== '', fn ($query) => $query->where('vehicle_reference', $vehicle))
            ->when($status, fn ($query) => $query->where('status', $status));

        $all = (clone $base)->orderBy('spent_at')->get();
        $costs = $all->groupBy('currency')->map(
            fn (Collection $rows) => round($rows->sum(fn (Expense $row) => (float) $row->amount), 2)
        )->all();

        [$distance, $efficiencyLiters] = $this->fullTankDistance($all->where('status', 'approved'));
        $evidenceComplete = $all->filter(fn (Expense $row) => filled($row->vehicle_reference)
            && $row->fuel_liters !== null
            && $row->odometer_km !== null
            && filled($row->receipt_path)
        )->count();

        $expenses = (clone $base)
            ->with(['salesman.user', 'reviewer', 'enteredBy'])
            ->orderByDesc('spent_at')
            ->paginate(min(100, max(10, $request->integer('per_page', 30))))
            ->withQueryString();

        $vehicles = Expense::query()
            ->where('category', 'fuel')
            ->whereNotNull('vehicle_reference')
            ->where('vehicle_reference', '<>', '')
            ->distinct()
            ->orderBy('vehicle_reference')
            ->pluck('vehicle_reference');

        return view('admin.fuel.index', [
            'expenses' => $expenses,
            'salesmen' => $salesmen,
            'vehicles' => $vehicles,
            'canManage' => $user->hasPermission('expenses:manage'),
            'timezone' => $timezone,
            'filters' => [
                'dateFrom' => $dateFrom,
                'dateTo' => $dateTo,
                'salesman' => $selectedSalesman?->uuid,
                'vehicle' => $vehicle,
                'status' => $status,
            ],
            'summary' => [
                'entries' => $all->count(),
                'liters' => round($all->sum(fn (Expense $row) => (float) ($row->fuel_liters ?? 0)), 3),
                'costs' => $costs,
                'full_tank_distance_km' => round($distance, 2),
                'km_per_liter' => $efficiencyLiters > 0 ? round($distance / $efficiencyLiters, 2) : null,
                'evidence_coverage' => $all->count() > 0
                    ? round(($evidenceComplete / $all->count()) * 100, 1)
                    : 0,
            ],
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate($this->managementRules(true));
        $salesman = Salesman::query()
            ->with('user')
            ->where('uuid', $validated['salesman'])
            ->where('is_active', true)
            ->firstOrFail();

        $spentAt = $this->managementSpentAt($request, $validated['spent_at']);
        $uuid = (string) Str::uuid();
        $compactUuid = strtoupper(substr(str_replace('-', '', $uuid), 0, 8));
        $device = Device::query()
            ->where('salesman_id', $salesman->id)
            ->orderByDesc('is_active')
            ->orderByDesc('last_seen_at')
            ->orderByDesc('id')
            ->first();

        $expense = Expense::create([
            'uuid' => $uuid,
            'user_id' => $salesman->user_id,
            'salesman_id' => $salesman->id,
            'device_id' => $device?->id,
            'entered_by' => $request->user()->id,
            'entry_source' => 'management_web',
            'expense_number' => 'EXP-'.$spentAt->format('Ymd').'-'.$compactUuid,
            'spent_at' => $spentAt,
            'category' => 'fuel',
            'currency' => strtoupper($validated['currency']),
            'amount' => round((float) $validated['amount'], 4),
            'fuel_liters' => round((float) $validated['fuel_liters'], 3),
            'fuel_unit_price' => round((float) $validated['amount'] / (float) $validated['fuel_liters'], 4),
            'odometer_km' => round((float) $validated['odometer_km'], 2),
            'vehicle_reference' => trim($validated['vehicle_reference']),
            'full_tank' => (bool) ($validated['full_tank'] ?? false),
            'merchant' => trim($validated['merchant']),
            'reference_number' => $validated['reference_number'] ?? null,
            'latitude' => null,
            'longitude' => null,
            'accuracy' => null,
            'status' => 'pending',
            'notes' => $validated['notes'] ?? null,
            'correction_reason' => trim($validated['correction_reason']),
        ]);

        if (($validated['receipt'] ?? null) instanceof UploadedFile) {
            $this->storeReceipt($expense, $validated['receipt']);
            $expense->refresh();
        }

        $audit->record('fuel.management_created', $expense, [], $this->auditValues($expense));

        return redirect()
            ->route('admin.fuel.index')
            ->with('status', 'Management fuel entry created and sent for approval.');
    }

    public function update(
        Request $request,
        Expense $expense,
        AuditLogger $audit,
    ): RedirectResponse {
        abort_unless($expense->category === 'fuel', 404);

        $validated = $request->validate($this->managementRules(false));
        $before = $this->auditValues($expense);
        $spentAt = $this->managementSpentAt($request, $validated['spent_at']);

        $expense->update([
            'spent_at' => $spentAt,
            'currency' => strtoupper($validated['currency']),
            'amount' => round((float) $validated['amount'], 4),
            'fuel_liters' => round((float) $validated['fuel_liters'], 3),
            'fuel_unit_price' => round((float) $validated['amount'] / (float) $validated['fuel_liters'], 4),
            'odometer_km' => round((float) $validated['odometer_km'], 2),
            'vehicle_reference' => trim($validated['vehicle_reference']),
            'full_tank' => (bool) ($validated['full_tank'] ?? false),
            'merchant' => trim($validated['merchant']),
            'reference_number' => $validated['reference_number'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'correction_reason' => trim($validated['correction_reason']),
            'status' => 'pending',
            'reviewed_by' => null,
            'reviewed_at' => null,
            'review_note' => null,
        ]);

        if (($validated['receipt'] ?? null) instanceof UploadedFile) {
            $this->storeReceipt($expense, $validated['receipt']);
        }

        $expense->refresh();
        $audit->record('fuel.management_corrected', $expense, $before, $this->auditValues($expense));

        return redirect()
            ->route('admin.fuel.index')
            ->with('status', 'Fuel entry corrected and returned to pending approval.');
    }

    public function receipt(Expense $expense): StreamedResponse
    {
        abort_unless($expense->category === 'fuel' && filled($expense->receipt_path), 404);
        abort_unless(Storage::disk('public')->exists($expense->receipt_path), 404);

        return Storage::disk('public')->download(
            $expense->receipt_path,
            basename($expense->receipt_path),
        );
    }

    private function managementRules(bool $creating): array
    {
        $rules = [
            'spent_at' => ['required', 'date'],
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999.9999'],
            'fuel_liters' => ['required', 'numeric', 'gt:0', 'max:999999999.999'],
            'odometer_km' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
            'vehicle_reference' => ['required', 'string', 'max:120'],
            'full_tank' => ['nullable', 'boolean'],
            'merchant' => ['required', 'string', 'max:160'],
            'reference_number' => ['nullable', 'string', 'max:160'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'correction_reason' => ['required', 'string', 'max:5000'],
            'receipt' => ['nullable', 'image', 'max:5120'],
        ];

        if ($creating) {
            $rules['salesman'] = ['required', 'uuid'];
        }

        return $rules;
    }

    private function managementSpentAt(Request $request, string $value): CarbonImmutable
    {
        $request->user()->loadMissing('tenant');
        $timezone = $request->user()->tenant?->timezone ?: config('app.timezone', 'UTC');
        $spentAt = CarbonImmutable::parse($value, $timezone)->utc();

        if ($spentAt->gt(now()->addMinutes(5))) {
            throw ValidationException::withMessages([
                'spent_at' => 'Fuel time cannot be more than five minutes in the future.',
            ]);
        }

        return $spentAt;
    }

    private function storeReceipt(Expense $expense, UploadedFile $file): void
    {
        $oldPath = $expense->receipt_path;
        $path = $file->store('fuel-receipts/'.$expense->uuid, 'public');

        $expense->update([
            'receipt_path' => $path,
            'receipt_mime_type' => $file->getMimeType(),
            'receipt_size_bytes' => $file->getSize(),
            'receipt_uploaded_at' => now(),
        ]);

        if ($oldPath && $oldPath !== $path) {
            Storage::disk('public')->delete($oldPath);
        }
    }

    private function auditValues(Expense $expense): array
    {
        return [
            'salesman_id' => $expense->salesman_id,
            'spent_at' => $expense->spent_at?->toIso8601String(),
            'vehicle_reference' => $expense->vehicle_reference,
            'merchant' => $expense->merchant,
            'fuel_liters' => $expense->fuel_liters,
            'fuel_unit_price' => $expense->fuel_unit_price,
            'amount' => $expense->amount,
            'currency' => $expense->currency,
            'odometer_km' => $expense->odometer_km,
            'full_tank' => (bool) $expense->full_tank,
            'reference_number' => $expense->reference_number,
            'notes' => $expense->notes,
            'receipt_path' => $expense->receipt_path,
            'status' => $expense->status,
            'entry_source' => $expense->entry_source,
            'entered_by' => $expense->entered_by,
            'correction_reason' => $expense->correction_reason,
        ];
    }

    private function fullTankDistance(Collection $rows): array
    {
        $distance = 0.0;
        $liters = 0.0;

        foreach ($rows
            ->filter(fn (Expense $row) => $row->full_tank
                && filled($row->vehicle_reference)
                && $row->odometer_km !== null
                && (float) ($row->fuel_liters ?? 0) > 0
            )
            ->groupBy('vehicle_reference') as $vehicleRows) {
            $previous = null;
            foreach ($vehicleRows->sortBy('spent_at') as $row) {
                if ($previous !== null) {
                    $delta = (float) $row->odometer_km - (float) $previous->odometer_km;
                    if ($delta > 0 && $delta < 5000) {
                        $distance += $delta;
                        $liters += (float) $row->fuel_liters;
                    }
                }
                $previous = $row;
            }
        }

        return [$distance, $liters];
    }
}