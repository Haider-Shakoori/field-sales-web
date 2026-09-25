<x-layouts.app>
    <link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
    @php
        $filters = $heatMap['filters'];
        $summary = $heatMap['summary'];
        $moneyMetric = in_array($filters['metric'], ['sales', 'collections'], true);
    @endphp

    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">{{ __('Territory heat maps') }}</h1>
            <p class="mt-1 text-sm text-slate-400">{{ __('See where customer coverage, visits, sales and collections are strong or falling behind.') }}</p>
        </div>
        <a href="{{ route('admin.live-map') }}" class="rounded-xl border border-white/10 bg-slate-900 px-4 py-2.5 text-sm font-semibold text-slate-300 hover:bg-white/5">{{ __('Live map') }}</a>
    </div>

    <form method="GET" class="mb-5 grid gap-3 rounded-2xl border border-white/10 bg-slate-900 p-4 sm:grid-cols-2 xl:grid-cols-[180px_180px_180px_160px_auto]">
        <label><span class="mb-1 block text-xs text-slate-500">{{ __('From') }}</span><input type="date" name="date_from" value="{{ $filters['date_from'] }}" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5"></label>
        <label><span class="mb-1 block text-xs text-slate-500">{{ __('To') }}</span><input type="date" name="date_to" value="{{ $filters['date_to'] }}" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5"></label>
        <label><span class="mb-1 block text-xs text-slate-500">{{ __('Metric') }}</span><select name="metric" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5">@foreach($metrics as $metric)<option value="{{ $metric }}" @selected($filters['metric'] === $metric)>{{ __(str($metric)->title()->toString()) }}</option>@endforeach</select></label>
        <label><span class="mb-1 block text-xs text-slate-500">{{ __('Currency') }}</span><select name="currency" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5" {{ $moneyMetric ? '' : 'disabled' }}>@forelse($heatMap['currencies'] as $currency)<option value="{{ $currency }}" @selected($filters['currency'] === $currency)>{{ $currency }}</option>@empty<option value="AFN">AFN</option>@endforelse</select></label>
        <button class="self-end rounded-xl bg-indigo-500 px-4 py-2.5 text-sm font-semibold hover:bg-indigo-400">{{ __('Apply') }}</button>
    </form>

    <div class="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
        <div class="rounded-2xl border border-white/10 bg-slate-900 p-4"><p class="text-xs uppercase tracking-wide text-slate-500">{{ __('Coverage') }}</p><p class="mt-2 text-2xl font-bold">{{ number_format($summary['coverage_percent'], 1) }}%</p><p class="mt-1 text-xs text-slate-500">{{ $summary['visited_customers'] }}/{{ $summary['customers'] }} {{ __('customers visited') }}</p></div>
        <div class="rounded-2xl border border-white/10 bg-slate-900 p-4"><p class="text-xs uppercase tracking-wide text-slate-500">{{ __('Completed visits') }}</p><p class="mt-2 text-2xl font-bold">{{ number_format($summary['visits']) }}</p></div>
        <div class="rounded-2xl border border-white/10 bg-slate-900 p-4"><p class="text-xs uppercase tracking-wide text-slate-500">{{ __('Approved sales') }}</p><p class="mt-2 text-2xl font-bold">{{ $summary['currency'] }} {{ number_format($summary['sales'], 2) }}</p></div>
        <div class="rounded-2xl border border-white/10 bg-slate-900 p-4"><p class="text-xs uppercase tracking-wide text-slate-500">{{ __('Verified collections') }}</p><p class="mt-2 text-2xl font-bold">{{ $summary['currency'] }} {{ number_format($summary['collections'], 2) }}</p></div>
        <div class="rounded-2xl border border-white/10 bg-slate-900 p-4"><p class="text-xs uppercase tracking-wide text-slate-500">{{ __('Mapped customers') }}</p><p class="mt-2 text-2xl font-bold">{{ $summary['mapped_customers'] }}</p><p class="mt-1 text-xs text-slate-500">{{ $summary['territories'] }} {{ __('territories') }}</p></div>
    </div>

    <div class="grid gap-5 2xl:grid-cols-[minmax(0,1fr)_380px]">
        <section class="overflow-hidden rounded-2xl border border-white/10 bg-slate-900">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/10 px-4 py-3"><div><h2 class="font-semibold">{{ __(str($filters['metric'])->title()->toString()) }} {{ __('heat map') }}</h2><p class="mt-1 text-xs text-slate-500">{{ $filters['date_from'] }} → {{ $filters['date_to'] }} @if($moneyMetric) · {{ $filters['currency'] }} @endif</p></div><div class="flex items-center gap-3 text-xs text-slate-500"><span>{{ __('Low') }}</span><span class="h-2 w-24 rounded-full bg-gradient-to-r from-slate-700 via-sky-500 to-emerald-400"></span><span>{{ __('High') }}</span></div></div>
            <div id="territory-heat-map" class="h-[680px] bg-slate-950"></div>
        </section>

        <aside class="space-y-5">
            <section class="rounded-2xl border border-white/10 bg-slate-900"><div class="border-b border-white/10 px-4 py-3"><h2 class="font-semibold">{{ __('Coverage attention') }}</h2><p class="mt-1 text-xs text-slate-500">{{ __('Territories with the lowest customer visit coverage in the selected period.') }}</p></div><div class="divide-y divide-white/10">@forelse($heatMap['under_covered'] as $row)<div class="p-4"><div class="flex items-center justify-between gap-3"><div class="min-w-0"><p class="truncate text-sm font-semibold">{{ $row['name'] }}</p><p class="mt-1 text-xs text-slate-500">{{ $row['visited_customers'] }}/{{ $row['customers'] }} {{ __('customers visited') }}</p></div><span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $row['coverage_percent'] < 50 ? 'bg-rose-500/10 text-rose-300' : ($row['coverage_percent'] < 80 ? 'bg-amber-500/10 text-amber-300' : 'bg-emerald-500/10 text-emerald-300') }}">{{ number_format($row['coverage_percent'], 1) }}%</span></div></div>@empty<div class="p-6 text-sm text-slate-500">{{ __('No territory coverage data is available.') }}</div>@endforelse</div></section>
            <section class="rounded-2xl border border-white/10 bg-slate-900 p-4"><h2 class="font-semibold">{{ __('Map reading') }}</h2><ul class="mt-3 space-y-2 text-xs leading-5 text-slate-400"><li>{{ __('Territory shading is normalized within the selected period and metric.') }}</li><li>{{ __('Customer circles use the same metric at customer level; larger circles indicate stronger activity.') }}</li><li>{{ __('Coverage counts a customer once when at least one completed visit exists in the selected period.') }}</li><li>{{ __('Sales and collections never combine currencies; choose a currency before comparing monetary performance.') }}</li></ul></section>
        </aside>
    </div>

    <script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
    <script>
        (() => {
            const territories = @json($heatMap['territories']);
            const points = @json($heatMap['points']);
            const metric = @json($filters['metric']);
            const currency = @json($filters['currency']);
            const map = L.map('territory-heat-map').setView([34.5553, 69.2075], 10);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom: 19, attribution: '&copy; OpenStreetMap contributors'}).addTo(map);
            const bounds = [];

            const metricText = (row) => {
                if (metric === 'coverage') return row.coverage_percent != null
                    ? Number(row.coverage_percent).toFixed(1) + '% coverage'
                    : (row.visited ? 'Visited' : 'Not visited');
                if (metric === 'visits') return Number(row.visits).toLocaleString() + ' visits';
                if (metric === 'sales') return currency + ' ' + Number(row.sales).toLocaleString(undefined, {maximumFractionDigits: 2});
                return currency + ' ' + Number(row.collections).toLocaleString(undefined, {maximumFractionDigits: 2});
            };

            const territoryColor = (row) => {
                const intensity = Math.max(0, Math.min(1, Number(row.intensity || 0)));
                if (metric === 'coverage') {
                    const hue = 5 + intensity * 120;
                    return `hsl(${hue} 72% 48%)`;
                }
                return `hsl(${215 - intensity * 35} ${55 + intensity * 25}% ${54 - intensity * 12}%)`;
            };

            const polygonLayer = (row) => {
                const polygon = row.polygon;
                if (!polygon) return null;
                const style = {color: territoryColor(row), weight: 2, fillColor: territoryColor(row), fillOpacity: .18 + Number(row.intensity || 0) * .42};
                try {
                    if (polygon.type === 'Feature' || polygon.type === 'FeatureCollection' || polygon.type === 'Polygon' || polygon.type === 'MultiPolygon') {
                        return L.geoJSON(polygon, {style}).addTo(map);
                    }
                    if (Array.isArray(polygon)) {
                        return L.polygon(polygon, style).addTo(map);
                    }
                } catch (_) {}
                return null;
            };

            for (const row of territories) {
                const layer = polygonLayer(row);
                if (!layer) continue;
                layer.bindPopup(`<strong>${row.name}</strong><br>${metricText(row)}<br>${row.visited_customers}/${row.customers} customers visited<br>${row.visits} completed visits`);
                try { bounds.push(...layer.getBounds().getNorthEast ? [layer.getBounds().getNorthEast(), layer.getBounds().getSouthWest()] : []); } catch (_) {}
            }

            for (const point of points) {
                const intensity = Math.max(0, Math.min(1, Number(point.intensity || 0)));
                const color = metric === 'coverage' ? (point.visited ? '#10b981' : '#f43f5e') : `hsl(${215 - intensity * 35} 75% ${52 - intensity * 10}%)`;
                const marker = L.circleMarker([point.latitude, point.longitude], {radius: 5 + intensity * 13, color, weight: 2, fillColor: color, fillOpacity: .3 + intensity * .55}).addTo(map);
                marker.bindPopup(`<strong>${point.name}</strong><br>${point.code}<br>${metricText(point)}<br>${point.visits} completed visits`);
                bounds.push([point.latitude, point.longitude]);
            }

            if (bounds.length > 0) map.fitBounds(bounds, {padding: [30, 30], maxZoom: 15});
        })();
    </script>
</x-layouts.app>
