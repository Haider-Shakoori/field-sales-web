<x-layouts.app>
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold">Reports</h1>
            <p class="mt-1 text-sm text-slate-400">Tenant-scoped operational reporting. Currency values are never combined across currencies.</p>
        </div>
        <a
            href="{{ route('admin.reports.csv', request()->query()) }}"
            class="rounded-lg bg-emerald-500 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-emerald-400"
        >Export CSV</a>
    </div>

    <div class="mb-5 flex flex-wrap gap-2">
        @foreach($types as $reportType)
            <a
                href="{{ route('admin.reports.index', array_merge(request()->except('type'), ['type' => $reportType])) }}"
                class="rounded-lg px-3 py-2 text-sm {{ $type === $reportType ? 'bg-sky-500 text-slate-950' : 'bg-white/5 text-slate-300 hover:bg-white/10' }}"
            >{{ str($reportType)->title() }}</a>
        @endforeach
    </div>

    <form method="GET" action="{{ route('admin.reports.index') }}" class="mb-6 grid gap-3 rounded-2xl border border-white/10 bg-slate-900 p-4 md:grid-cols-6">
        <input type="hidden" name="type" value="{{ $type }}">
        <label class="text-sm text-slate-300">
            <span class="mb-1 block text-xs text-slate-400">From</span>
            <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="w-full rounded-lg border border-white/10 bg-slate-950 px-3 py-2">
        </label>
        <label class="text-sm text-slate-300">
            <span class="mb-1 block text-xs text-slate-400">To</span>
            <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="w-full rounded-lg border border-white/10 bg-slate-950 px-3 py-2">
        </label>
        <label class="text-sm text-slate-300">
            <span class="mb-1 block text-xs text-slate-400">Branch</span>
            <select name="branch" class="w-full rounded-lg border border-white/10 bg-slate-950 px-3 py-2">
                <option value="">All visible</option>
                @foreach($options['branches'] as $branch)
                    <option value="{{ $branch->uuid }}" @selected(($filters['branch_uuid'] ?? null) === $branch->uuid)>{{ $branch->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-sm text-slate-300">
            <span class="mb-1 block text-xs text-slate-400">Territory</span>
            <select name="territory" class="w-full rounded-lg border border-white/10 bg-slate-950 px-3 py-2">
                <option value="">All visible</option>
                @foreach($options['territories'] as $territory)
                    <option value="{{ $territory->uuid }}" @selected(($filters['territory_uuid'] ?? null) === $territory->uuid)>{{ $territory->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-sm text-slate-300">
            <span class="mb-1 block text-xs text-slate-400">Salesman</span>
            <select name="salesman" class="w-full rounded-lg border border-white/10 bg-slate-950 px-3 py-2">
                <option value="">All visible</option>
                @foreach($options['salesmen'] as $salesman)
                    <option value="{{ $salesman->uuid }}" @selected(($filters['salesman_uuid'] ?? null) === $salesman->uuid)>{{ $salesman->full_name }} · {{ $salesman->employee_code }}</option>
                @endforeach
            </select>
        </label>
        <div class="flex items-end">
            <button class="w-full rounded-lg bg-sky-500 px-4 py-2 font-semibold text-slate-950 hover:bg-sky-400">Apply</button>
        </div>
    </form>

    <section class="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        @foreach($report['summary'] as $label => $value)
            <div class="rounded-2xl border border-white/10 bg-slate-900 p-4">
                <div class="text-xs uppercase tracking-wide text-slate-500">{{ $label }}</div>
                <div class="mt-2 text-lg font-semibold">{{ $value }}</div>
            </div>
        @endforeach
    </section>

    <section class="overflow-hidden rounded-2xl border border-white/10 bg-slate-900">
        <div class="border-b border-white/10 px-5 py-4">
            <h2 class="font-semibold">{{ $report['title'] }}</h2>
            <p class="mt-1 text-xs text-slate-400">{{ $report['period'][0] }} through {{ $report['period'][1] }}</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-white/5 text-xs uppercase tracking-wide text-slate-400">
                    <tr>
                        @foreach($report['columns'] as $label)
                            <th class="whitespace-nowrap px-5 py-3">{{ $label }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/10">
                    @forelse($report['rows'] as $row)
                        <tr>
                            @foreach(array_keys($report['columns']) as $key)
                                <td class="whitespace-nowrap px-5 py-4 text-slate-300">{{ $row[$key] ?? '—' }}</td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="{{ count($report['columns']) }}" class="px-5 py-10 text-center text-slate-400">No data matches these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-layouts.app>
