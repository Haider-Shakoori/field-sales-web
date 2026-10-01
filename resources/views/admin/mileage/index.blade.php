<x-layouts.app title="Mileage">
<div class="mb-6 flex flex-wrap items-start justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold tracking-tight">{{ __('Mileage') }}</h1>
        <p class="mt-1 text-sm text-slate-400">{{ __('Reconcile GPS travel, odometer readings, and approved fuel expenses.') }}</p>
    </div>
    @if($canManage)
        <span class="rounded-full border border-emerald-400/20 bg-emerald-500/10 px-3 py-1 text-xs font-semibold text-emerald-300">Odometer corrections enabled</span>
    @endif
</div>

@if(session('status'))
    <div class="mb-6 rounded-xl border border-emerald-400/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">{{ session('status') }}</div>
@endif

<form method="GET" class="mb-6 grid gap-3 rounded-2xl border border-white/10 bg-slate-900 p-4 md:grid-cols-4">
    <label class="text-sm">
        <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('From') }}</span>
        <input type="date" name="date_from" value="{{ $filters['date_from'] }}" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5">
    </label>
    <label class="text-sm">
        <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('To') }}</span>
        <input type="date" name="date_to" value="{{ $filters['date_to'] }}" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5">
    </label>
    <label class="text-sm">
        <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Salesman') }}</span>
        <select name="salesman" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5">
            <option value="">{{ __('All salesmen') }}</option>
            @foreach($salesmen as $salesman)
                <option value="{{ $salesman->uuid }}" @selected($filters['salesman'] === $salesman->uuid)>
                    {{ $salesman->employee_code }} · {{ $salesman->full_name }}
                </option>
            @endforeach
        </select>
    </label>
    <div class="flex items-end gap-2">
        <button class="flex-1 rounded-xl bg-indigo-500 px-4 py-2.5 text-sm font-semibold hover:bg-indigo-400">{{ __('Apply') }}</button>
        <a href="{{ route('admin.mileage.index') }}" class="rounded-xl bg-white/10 px-4 py-2.5 text-sm font-semibold hover:bg-white/15">{{ __('Reset') }}</a>
    </div>
</form>

<section class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <div class="rounded-2xl border border-white/10 bg-slate-900 p-5">
        <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('Tracked sessions') }}</p>
        <p class="mt-2 text-3xl font-bold">{{ $summary['sessions'] }}</p>
    </div>
    <div class="rounded-2xl border border-white/10 bg-slate-900 p-5">
        <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('Travel distance') }}</p>
        <p class="mt-2 text-3xl font-bold">{{ number_format($summary['distance_km'], 1) }} <span class="text-base font-medium text-slate-500">km</span></p>
    </div>
    <div class="rounded-2xl border border-white/10 bg-slate-900 p-5">
        <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('Fuel recorded') }}</p>
        <p class="mt-2 text-3xl font-bold">{{ number_format($summary['fuel_liters'], 2) }} <span class="text-base font-medium text-slate-500">L</span></p>
    </div>
    <div class="rounded-2xl border border-white/10 bg-slate-900 p-5">
        <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('Average efficiency') }}</p>
        <p class="mt-2 text-3xl font-bold">
            {{ $summary['km_per_liter'] === null ? '—' : number_format($summary['km_per_liter'], 2) }}
            <span class="text-base font-medium text-slate-500">km/L</span>
        </p>
    </div>
</section>

@if($summary['fuel_cost_by_currency'])
    <div class="mb-6 flex flex-wrap gap-2">
        <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Approved fuel cost') }}</span>
        @foreach($summary['fuel_cost_by_currency'] as $currency => $amount)
            <span class="rounded-full border border-white/10 bg-slate-900 px-3 py-1 text-xs font-semibold">
                {{ $currency }} {{ number_format($amount, 2) }}
            </span>
        @endforeach
    </div>
@endif

<section class="overflow-hidden rounded-2xl border border-white/10 bg-slate-900">
    <div class="overflow-x-auto">
        <table class="min-w-full text-left text-sm">
            <thead class="bg-slate-950/70 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">{{ __('Date') }}</th>
                    <th class="px-4 py-3">{{ __('Salesman') }}</th>
                    <th class="px-4 py-3">{{ __('Vehicle') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('GPS km') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Odometer km') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Variance') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Fuel liters') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Efficiency') }}</th>
                    <th class="px-4 py-3">{{ __('Fuel cost') }}</th>
                    @if($canManage)<th class="px-4 py-3">{{ __('Correction') }}</th>@endif
                </tr>
            </thead>
            <tbody class="divide-y divide-white/5">
                @forelse($rows as $row)
                    @php($session = $row['session'])
                    <tr class="align-top hover:bg-white/[0.025]">
                        <td class="whitespace-nowrap px-4 py-4">
                            <p class="font-medium">{{ $session->date?->format('Y-m-d') }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ __(str($session->status)->title()->toString()) }}</p>
                            @if(collect($session->corrections)->where('type', 'management_mileage_correction')->isNotEmpty())
                                <span class="mt-1 inline-block rounded-full bg-amber-500/15 px-2 py-0.5 text-[11px] text-amber-300">Corrected</span>
                            @endif
                        </td>
                        <td class="px-4 py-4">
                            <p class="font-medium">{{ $session->salesman?->full_name }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $session->salesman?->employee_code }}</p>
                        </td>
                        <td class="px-4 py-4 text-slate-300">{{ $session->vehicle_reference ?: '—' }}</td>
                        <td class="px-4 py-4 text-right font-mono">{{ number_format($row['gps_distance_km'], 2) }}</td>
                        <td class="px-4 py-4 text-right font-mono">
                            {{ $row['odometer_distance_km'] === null ? '—' : number_format($row['odometer_distance_km'], 2) }}
                            @if($session->odometer_start_km !== null || $session->odometer_end_km !== null)
                                <div class="mt-1 text-[11px] text-slate-500">{{ $session->odometer_start_km ?? '—' }} → {{ $session->odometer_end_km ?? '—' }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-4 text-right font-mono {{ $row['distance_variance_km'] !== null && abs($row['distance_variance_km']) > 5 ? 'text-amber-300' : 'text-slate-400' }}">
                            {{ $row['distance_variance_km'] === null ? '—' : number_format($row['distance_variance_km'], 2) }}
                        </td>
                        <td class="px-4 py-4 text-right font-mono">{{ number_format($row['fuel_liters'], 2) }}</td>
                        <td class="px-4 py-4 text-right font-mono">{{ $row['km_per_liter'] === null ? '—' : number_format($row['km_per_liter'], 2).' km/L' }}</td>
                        <td class="px-4 py-4">
                            <div class="flex flex-wrap gap-1">
                                @forelse($row['fuel_cost_by_currency'] as $currency => $amount)
                                    <span class="rounded-lg bg-white/5 px-2 py-1 text-xs">{{ $currency }} {{ number_format($amount, 2) }}</span>
                                @empty
                                    <span class="text-slate-600">—</span>
                                @endforelse
                            </div>
                        </td>
                        @if($canManage)
                            <td class="px-4 py-4">
                                <details class="min-w-[12rem]">
                                    <summary class="cursor-pointer font-semibold text-amber-300">{{ __('Correct') }}</summary>
                                    <form method="POST" action="{{ route('admin.mileage.update', $session) }}" class="mt-3 grid min-w-[30rem] grid-cols-2 gap-2 rounded-xl border border-white/10 bg-slate-950 p-3">
                                        @csrf
                                        @method('PATCH')
                                        <label class="text-xs text-slate-400">
                                            Vehicle / plate
                                            <input name="vehicle_reference" maxlength="120" value="{{ $session->vehicle_reference }}" class="mt-1 w-full rounded-lg border border-white/10 bg-slate-900 px-2 py-2 text-sm text-white">
                                        </label>
                                        <div></div>
                                        <label class="text-xs text-slate-400">
                                            Start odometer
                                            <input type="number" step="0.01" min="0" name="odometer_start_km" value="{{ $session->odometer_start_km }}" class="mt-1 w-full rounded-lg border border-white/10 bg-slate-900 px-2 py-2 text-sm text-white">
                                        </label>
                                        <label class="text-xs text-slate-400">
                                            End odometer
                                            <input type="number" step="0.01" min="0" name="odometer_end_km" value="{{ $session->odometer_end_km }}" class="mt-1 w-full rounded-lg border border-white/10 bg-slate-900 px-2 py-2 text-sm text-white">
                                        </label>
                                        <label class="col-span-2 text-xs text-slate-400">
                                            Required reason
                                            <textarea name="correction_reason" required maxlength="5000" rows="2" class="mt-1 w-full rounded-lg border border-amber-400/20 bg-slate-900 px-2 py-2 text-sm text-white" placeholder="Why is this odometer/vehicle value being corrected?"></textarea>
                                        </label>
                                        <p class="col-span-2 text-xs text-slate-500">GPS distance is preserved as the independent reconciliation baseline.</p>
                                        <button class="col-span-2 rounded-lg bg-amber-500 px-3 py-2 font-semibold text-slate-950">Save audited correction</button>
                                    </form>
                                </details>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $canManage ? 10 : 9 }}" class="px-4 py-12 text-center text-slate-500">{{ __('No mileage records were found for this period.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

<div class="mt-4 rounded-xl border border-white/10 bg-slate-900/60 px-4 py-3 text-xs leading-5 text-slate-500">
    {{ __('GPS distance ignores mock locations, points worse than 50 m accuracy, and implausible segments above 200 km/h. When both odometer readings exist, odometer distance is used for fuel-efficiency calculations and GPS remains the reconciliation baseline.') }}
</div>
</x-layouts.app>
