<x-layouts.app>
<div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-semibold text-indigo-600 dark:text-indigo-400">{{ $date }} · {{ $timezone }}</p>
            <h1 class="mt-1 text-3xl font-black tracking-tight">Field operations dashboard</h1>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Attendance, tracking, visits, sales and collections from real operational data.</p>
        </div>
        <a href="{{ route('admin.locations') }}" class="rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-soft hover:bg-indigo-500">Open tracking</a>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach([
            ['Active salesmen',$metrics['active_salesmen'],'Team'],
            ['Working now',$metrics['working'],'Attendance'],
            ['Tracking online',$metrics['tracking_online'],'GPS'],
            ['Visits today',$metrics['visits'],'Coverage'],
            ['Orders today',$metrics['orders'],'Sales'],
            ['Sales value',number_format($metrics['sales'],2).' AFN','Revenue'],
            ['Collections',number_format($metrics['collections'],2).' AFN','Cash'],
            ['Not started',$metrics['not_started'],'Follow-up'],
        ] as [$label,$value,$hint])
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-slate-900">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ $hint }}</p>
            <p class="mt-3 text-2xl font-black">{{ $value }}</p>
            <p class="mt-1 text-sm text-slate-500">{{ $label }}</p>
        </div>
        @endforeach
    </div>

    <div class="grid gap-6 xl:grid-cols-[1.4fr_.8fr]">
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-white/10 dark:bg-slate-900">
            <div class="border-b border-slate-200 p-5 dark:border-white/10">
                <h2 class="font-bold">Live field map</h2>
                <p class="mt-1 text-sm text-slate-500">Latest accepted location for your scoped team. Stale devices remain visible for review.</p>
            </div>
            <div id="field-map" class="h-[430px] bg-slate-100 dark:bg-slate-800"></div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white dark:border-white/10 dark:bg-slate-900">
            <div class="border-b border-slate-200 p-5 dark:border-white/10">
                <h2 class="font-bold">Team status</h2>
                <p class="mt-1 text-sm text-slate-500">{{ $metrics['tracking_offline'] }} active sessions currently stale/offline.</p>
            </div>
            <div class="divide-y divide-slate-100 dark:divide-white/10">
                @forelse($team as $salesman)
                    @php
                        $location = $salesman->currentLocation;
                        $status = !$location ? 'No location' : ($location->recorded_at && $location->recorded_at->gte($staleBefore) ? 'Online' : 'Stale');
                    @endphp
                    <div class="flex items-center gap-3 p-4">
                        <div class="grid h-10 w-10 place-items-center rounded-full bg-indigo-100 font-bold text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300">{{ strtoupper(substr($salesman->user?->name ?? '?',0,1)) }}</div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-semibold">{{ $salesman->user?->name }}</p>
                            <p class="text-xs text-slate-500">{{ $salesman->employee_code }} · {{ $location?->recorded_at?->diffForHumans() ?? 'Never seen' }}</p>
                        </div>
                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $status === 'Online' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300' }}">{{ $status }}</span>
                    </div>
                @empty
                    <div class="p-8 text-center text-sm text-slate-500">No salesmen are in your current scope.</div>
                @endforelse
            </div>
        </section>
    </div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const rows = @json($team->map(fn($s) => $s->currentLocation ? [
        'name' => $s->user?->name,
        'code' => $s->employee_code,
        'lat' => (float)$s->currentLocation->latitude,
        'lng' => (float)$s->currentLocation->longitude,
        'at' => $s->currentLocation->recorded_at?->toISOString(),
    ] : null)->filter()->values());

    const center = rows.length ? [rows[0].lat, rows[0].lng] : [34.5553, 69.2075];
    const map = L.map('field-map').setView(center, rows.length ? 12 : 10);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom: 19, attribution: '&copy; OpenStreetMap'}).addTo(map);
    rows.forEach(row => L.marker([row.lat,row.lng]).addTo(map).bindPopup('<strong>'+row.name+'</strong><br>'+row.code+'<br>'+row.at));
    if (rows.length > 1) map.fitBounds(rows.map(r => [r.lat,r.lng]), {padding:[30,30]});
});
</script>
</x-layouts.app>
