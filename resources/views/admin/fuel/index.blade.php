<x-layouts.app title="Fuel Management">
    <div class="space-y-6">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-indigo-300">Fleet operations</p>
            <h1 class="mt-2 text-3xl font-bold">Fuel Management</h1>
            <p class="mt-2 text-sm text-slate-400">Vehicle fuel cost, receipt evidence, odometer history and full-tank efficiency.</p>
        </div>

        <form method="GET" class="grid gap-3 rounded-2xl border border-white/10 bg-slate-900 p-4 md:grid-cols-5">
            <input type="date" name="date_from" value="{{ $filters['dateFrom'] }}" class="rounded-xl border border-white/10 bg-slate-950 px-3 py-2">
            <input type="date" name="date_to" value="{{ $filters['dateTo'] }}" class="rounded-xl border border-white/10 bg-slate-950 px-3 py-2">
            <select name="salesman" class="rounded-xl border border-white/10 bg-slate-950 px-3 py-2">
                <option value="">All salesmen</option>
                @foreach($salesmen as $salesman)
                    <option value="{{ $salesman->uuid }}" @selected($filters['salesman'] === $salesman->uuid)>
                        {{ $salesman->user?->name ?? $salesman->employee_code }}
                    </option>
                @endforeach
            </select>
            <select name="vehicle" class="rounded-xl border border-white/10 bg-slate-950 px-3 py-2">
                <option value="">All vehicles</option>
                @foreach($vehicles as $vehicle)
                    <option value="{{ $vehicle }}" @selected($filters['vehicle'] === $vehicle)>{{ $vehicle }}</option>
                @endforeach
            </select>
            <div class="flex gap-2">
                <select name="status" class="min-w-0 flex-1 rounded-xl border border-white/10 bg-slate-950 px-3 py-2">
                    <option value="">All statuses</option>
                    @foreach(\App\Models\Expense::STATUSES as $status)
                        <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ str($status)->title() }}</option>
                    @endforeach
                </select>
                <button class="rounded-xl bg-indigo-500 px-4 py-2 font-semibold">Filter</button>
            </div>
        </form>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
            <div class="rounded-2xl border border-white/10 bg-slate-900 p-5"><div class="text-sm text-slate-400">Fuel entries</div><div class="mt-2 text-3xl font-bold">{{ number_format($summary['entries']) }}</div></div>
            <div class="rounded-2xl border border-white/10 bg-slate-900 p-5"><div class="text-sm text-slate-400">Liters</div><div class="mt-2 text-3xl font-bold">{{ number_format($summary['liters'], 3) }}</div></div>
            <div class="rounded-2xl border border-white/10 bg-slate-900 p-5"><div class="text-sm text-slate-400">Full-tank km/L</div><div class="mt-2 text-3xl font-bold">{{ $summary['km_per_liter'] === null ? '—' : number_format($summary['km_per_liter'], 2) }}</div><div class="mt-1 text-xs text-slate-500">{{ number_format($summary['full_tank_distance_km'], 1) }} km measured</div></div>
            <div class="rounded-2xl border border-white/10 bg-slate-900 p-5"><div class="text-sm text-slate-400">Evidence complete</div><div class="mt-2 text-3xl font-bold">{{ number_format($summary['evidence_coverage'], 1) }}%</div><div class="mt-1 text-xs text-slate-500">Vehicle + liters + odometer + receipt</div></div>
            <div class="rounded-2xl border border-white/10 bg-slate-900 p-5"><div class="text-sm text-slate-400">Fuel cost</div><div class="mt-2 space-y-1 text-lg font-bold">@forelse($summary['costs'] as $currency => $amount)<div>{{ number_format($amount, 2) }} {{ $currency }}</div>@empty<div>—</div>@endforelse</div></div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-white/10 bg-slate-900">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-white/5 text-left text-xs uppercase tracking-wide text-slate-400">
                        <tr>
                            <th class="px-4 py-3">Date</th><th class="px-4 py-3">Salesman</th><th class="px-4 py-3">Vehicle</th><th class="px-4 py-3">Station</th>
                            <th class="px-4 py-3 text-right">Liters</th><th class="px-4 py-3 text-right">Price/L</th><th class="px-4 py-3 text-right">Total</th>
                            <th class="px-4 py-3 text-right">Odometer</th><th class="px-4 py-3">Evidence</th><th class="px-4 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        @forelse($expenses as $expense)
                            <tr class="hover:bg-white/[0.03]">
                                <td class="px-4 py-3 whitespace-nowrap">{{ $expense->spent_at?->format('Y-m-d H:i') }}</td>
                                <td class="px-4 py-3">{{ $expense->salesman?->user?->name ?? '—' }}</td>
                                <td class="px-4 py-3 font-semibold">{{ $expense->vehicle_reference ?? 'Missing' }}</td>
                                <td class="px-4 py-3">{{ $expense->merchant ?? '—' }}</td>
                                <td class="px-4 py-3 text-right">{{ $expense->fuel_liters === null ? '—' : number_format((float) $expense->fuel_liters, 3) }}</td>
                                <td class="px-4 py-3 text-right">{{ $expense->fuel_unit_price === null ? '—' : number_format((float) $expense->fuel_unit_price, 2).' '.$expense->currency }}</td>
                                <td class="px-4 py-3 text-right font-semibold">{{ number_format((float) $expense->amount, 2) }} {{ $expense->currency }}</td>
                                <td class="px-4 py-3 text-right">{{ $expense->odometer_km === null ? '—' : number_format((float) $expense->odometer_km, 1) }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-1">
                                        @if($expense->full_tank)<span class="rounded-full bg-emerald-500/15 px-2 py-1 text-xs text-emerald-300">Full tank</span>@endif
                                        @if($expense->receipt_path)
                                            <a href="{{ route('admin.fuel.receipt', $expense) }}" class="rounded-full bg-indigo-500/15 px-2 py-1 text-xs text-indigo-300">Receipt</a>
                                        @else
                                            <span class="rounded-full bg-amber-500/15 px-2 py-1 text-xs text-amber-300">No receipt</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-3"><a class="font-semibold text-indigo-300 hover:text-indigo-200" href="{{ route('admin.expenses.show', $expense) }}">{{ str($expense->status)->title() }}</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="10" class="px-4 py-10 text-center text-slate-400">No fuel entries match these filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-white/10 px-4 py-3">{{ $expenses->links() }}</div>
        </div>
    </div>
</x-layouts.app>
