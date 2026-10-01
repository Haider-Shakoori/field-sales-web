<x-layouts.app title="Fuel Management">
    <div class="space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.2em] text-indigo-300">Fleet operations</p>
                <h1 class="mt-2 text-3xl font-bold">Fuel Management</h1>
                <p class="mt-2 text-sm text-slate-400">Vehicle fuel cost, receipt evidence, odometer history and full-tank efficiency.</p>
            </div>
            @if($canManage)
                <span class="rounded-full border border-emerald-400/20 bg-emerald-500/10 px-3 py-1 text-xs font-semibold text-emerald-300">Management corrections enabled</span>
            @endif
        </div>

        @if(session('status'))
            <div class="rounded-xl border border-emerald-400/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">{{ session('status') }}</div>
        @endif

        @if($canManage)
            <details class="rounded-2xl border border-indigo-400/20 bg-slate-900 p-4" @if($errors->any()) open @endif>
                <summary class="cursor-pointer list-none font-semibold text-indigo-200">
                    + Add management fuel entry
                    <span class="ml-2 text-xs font-normal text-slate-400">Requires a reason and remains pending until reviewed.</span>
                </summary>
                <form method="POST" action="{{ route('admin.fuel.store') }}" enctype="multipart/form-data" class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    @csrf
                    <label class="text-sm">
                        <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Salesman</span>
                        <select name="salesman" required class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5">
                            <option value="">Select salesman</option>
                            @foreach($salesmen as $salesman)
                                <option value="{{ $salesman->uuid }}" @selected(old('salesman') === $salesman->uuid)>
                                    {{ $salesman->employee_code }} · {{ $salesman->user?->name ?? $salesman->full_name }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                    <label class="text-sm">
                        <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Date & time</span>
                        <input type="datetime-local" name="spent_at" required value="{{ old('spent_at', now($timezone)->format('Y-m-dTH:i')) }}" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5">
                    </label>
                    <label class="text-sm">
                        <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Vehicle / plate</span>
                        <input name="vehicle_reference" required maxlength="120" value="{{ old('vehicle_reference') }}" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5">
                    </label>
                    <label class="text-sm">
                        <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Fuel station</span>
                        <input name="merchant" required maxlength="160" value="{{ old('merchant') }}" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5">
                    </label>
                    <label class="text-sm">
                        <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Liters</span>
                        <input type="number" step="0.001" min="0.001" name="fuel_liters" required value="{{ old('fuel_liters') }}" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5">
                    </label>
                    <label class="text-sm">
                        <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Total amount</span>
                        <input type="number" step="0.0001" min="0.0001" name="amount" required value="{{ old('amount') }}" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5">
                    </label>
                    <label class="text-sm">
                        <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Odometer km</span>
                        <input type="number" step="0.01" min="0" name="odometer_km" required value="{{ old('odometer_km') }}" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5">
                    </label>
                    <label class="text-sm">
                        <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Currency</span>
                        <input name="currency" required maxlength="3" value="{{ old('currency', 'AFN') }}" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5 uppercase">
                    </label>
                    <label class="text-sm">
                        <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Reference / fuel card</span>
                        <input name="reference_number" maxlength="160" value="{{ old('reference_number') }}" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5">
                    </label>
                    <label class="flex items-center gap-2 self-end rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5 text-sm">
                        <input type="hidden" name="full_tank" value="0">
                        <input type="checkbox" name="full_tank" value="1" @checked(old('full_tank'))>
                        Full tank
                    </label>
                    <label class="text-sm xl:col-span-2">
                        <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Receipt image</span>
                        <input type="file" name="receipt" accept="image/*" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2">
                    </label>
                    <label class="text-sm md:col-span-2">
                        <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Management reason</span>
                        <textarea name="correction_reason" required maxlength="5000" rows="2" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5" placeholder="Why is management entering this instead of the salesman?">{{ old('correction_reason') }}</textarea>
                    </label>
                    <label class="text-sm md:col-span-2">
                        <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Notes</span>
                        <textarea name="notes" maxlength="5000" rows="2" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5">{{ old('notes') }}</textarea>
                    </label>
                    <div class="md:col-span-2 xl:col-span-4">
                        <button class="rounded-xl bg-indigo-500 px-5 py-2.5 text-sm font-semibold hover:bg-indigo-400">Create pending fuel entry</button>
                    </div>
                </form>
                @if($errors->any())
                    <div class="mt-4 rounded-xl border border-rose-400/20 bg-rose-500/10 p-3 text-sm text-rose-200">
                        {{ $errors->first() }}
                    </div>
                @endif
            </details>
        @endif

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
                            <th class="px-4 py-3 text-right">Odometer</th><th class="px-4 py-3">Evidence</th><th class="px-4 py-3">Source</th><th class="px-4 py-3">Status</th>
                            @if($canManage)<th class="px-4 py-3">Correction</th>@endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        @forelse($expenses as $expense)
                            <tr class="align-top hover:bg-white/[0.03]">
                                <td class="px-4 py-3 whitespace-nowrap">{{ $expense->spent_at?->setTimezone($timezone)->format('Y-m-d H:i') }}</td>
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
                                <td class="px-4 py-3">
                                    @if($expense->entry_source === 'management_web')
                                        <span class="rounded-full bg-violet-500/15 px-2 py-1 text-xs text-violet-300">Management</span>
                                        <div class="mt-1 text-xs text-slate-500">{{ $expense->enteredBy?->name }}</div>
                                    @else
                                        <span class="rounded-full bg-sky-500/15 px-2 py-1 text-xs text-sky-300">Mobile</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3"><a class="font-semibold text-indigo-300 hover:text-indigo-200" href="{{ route('admin.expenses.show', $expense) }}">{{ str($expense->status)->title() }}</a></td>
                                @if($canManage)
                                    <td class="px-4 py-3">
                                        <details class="min-w-[12rem]">
                                            <summary class="cursor-pointer font-semibold text-amber-300">Correct</summary>
                                            <form method="POST" action="{{ route('admin.fuel.update', $expense) }}" enctype="multipart/form-data" class="mt-3 grid min-w-[38rem] grid-cols-2 gap-2 rounded-xl border border-white/10 bg-slate-950 p-3">
                                                @csrf
                                                @method('PATCH')
                                                <input type="datetime-local" name="spent_at" required value="{{ $expense->spent_at?->setTimezone($timezone)->format('Y-m-dTH:i') }}" class="rounded-lg border border-white/10 bg-slate-900 px-2 py-2">
                                                <input name="vehicle_reference" required maxlength="120" value="{{ $expense->vehicle_reference }}" placeholder="Vehicle" class="rounded-lg border border-white/10 bg-slate-900 px-2 py-2">
                                                <input name="merchant" required maxlength="160" value="{{ $expense->merchant }}" placeholder="Fuel station" class="rounded-lg border border-white/10 bg-slate-900 px-2 py-2">
                                                <input type="number" step="0.001" min="0.001" name="fuel_liters" required value="{{ $expense->fuel_liters }}" placeholder="Liters" class="rounded-lg border border-white/10 bg-slate-900 px-2 py-2">
                                                <input type="number" step="0.0001" min="0.0001" name="amount" required value="{{ $expense->amount }}" placeholder="Amount" class="rounded-lg border border-white/10 bg-slate-900 px-2 py-2">
                                                <input type="number" step="0.01" min="0" name="odometer_km" required value="{{ $expense->odometer_km }}" placeholder="Odometer" class="rounded-lg border border-white/10 bg-slate-900 px-2 py-2">
                                                <input name="currency" required maxlength="3" value="{{ $expense->currency }}" class="rounded-lg border border-white/10 bg-slate-900 px-2 py-2 uppercase">
                                                <input name="reference_number" maxlength="160" value="{{ $expense->reference_number }}" placeholder="Reference / fuel card" class="rounded-lg border border-white/10 bg-slate-900 px-2 py-2">
                                                <label class="flex items-center gap-2 rounded-lg border border-white/10 bg-slate-900 px-2 py-2">
                                                    <input type="hidden" name="full_tank" value="0">
                                                    <input type="checkbox" name="full_tank" value="1" @checked($expense->full_tank)> Full tank
                                                </label>
                                                <input type="file" name="receipt" accept="image/*" class="rounded-lg border border-white/10 bg-slate-900 px-2 py-1.5">
                                                <textarea name="notes" maxlength="5000" rows="2" placeholder="Notes" class="col-span-2 rounded-lg border border-white/10 bg-slate-900 px-2 py-2">{{ $expense->notes }}</textarea>
                                                <textarea name="correction_reason" required maxlength="5000" rows="2" placeholder="Required correction reason" class="col-span-2 rounded-lg border border-amber-400/20 bg-slate-900 px-2 py-2"></textarea>
                                                <p class="col-span-2 text-xs text-amber-300">Any reviewed entry will return to Pending after correction.</p>
                                                <button class="col-span-2 rounded-lg bg-amber-500 px-3 py-2 font-semibold text-slate-950">Save audited correction</button>
                                            </form>
                                        </details>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="{{ $canManage ? 12 : 11 }}" class="px-4 py-10 text-center text-slate-400">No fuel entries match these filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-white/10 px-4 py-3">{{ $expenses->links() }}</div>
        </div>
    </div>
</x-layouts.app>