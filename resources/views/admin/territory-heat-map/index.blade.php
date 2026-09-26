<x-layouts.app>
    <link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
    @php
        $filters = $heatMap['filters'];
        $summary = $heatMap['summary'];
        $moneyMetric = in_array($filters['metric'], ['sales', 'collections'], true);
        $metricLabels = [
            'coverage' => __('Coverage'),
            'visits' => __('Visits'),
            'density' => __('Customer density'),
            'stale' => __('Stale customers'),
            'sales' => __('Sales'),
            'collections' => __('Collections'),
        ];
        $attentionReasonLabels = [
            'coverage_below_threshold' => __('Coverage below threshold'),
            'stale_customer_share_high' => __('High stale-customer share'),
            'customer_coordinates_outside_polygon' => __('Customer GPS outside territory'),
            'customers_missing_coordinates' => __('Customers missing coordinates'),
        ];
    @endphp

    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">{{ __('Territory intelligence') }}</h1>
            <p class="mt-1 max-w-3xl text-sm text-slate-400">{{ __('Compare coverage, customer density, stale accounts, visits, sales, collections and geographic data quality from one management view.') }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if(auth()->user()->hasPermission('settings:view'))
                <a href="{{ route('organization.edit') }}" class="rounded-xl border border-white/10 bg-slate-900 px-4 py-2.5 text-sm font-semibold text-slate-300 hover:bg-white/5">{{ __('Intelligence settings') }}</a>
            @endif
            <a href="{{ route('admin.live-map') }}" class="rounded-xl border border-white/10 bg-slate-900 px-4 py-2.5 text-sm font-semibold text-slate-300 hover:bg-white/5">{{ __('Live map') }}</a>
        </div>
    </div>

    @if(!($heatMap['enabled'] ?? true))
        <div class="rounded-2xl border border-amber-400/20 bg-amber-500/10 p-6">
            <h2 class="font-semibold text-amber-200">{{ __('Territory intelligence is disabled') }}</h2>
            <p class="mt-2 text-sm text-amber-200/80">{{ __('Territory polygons and customer assignments remain available, but management territory analytics are turned off for this organization.') }}</p>
            @if(auth()->user()->hasPermission('settings:view'))
                <a href="{{ route('organization.edit') }}" class="mt-4 inline-flex rounded-xl bg-white/10 px-4 py-2 text-sm font-semibold text-slate-100 hover:bg-white/20">{{ __('Open organization settings') }}</a>
            @endif
        </div>
    @else
        <form method="GET" class="mb-5 grid gap-3 rounded-2xl border border-white/10 bg-slate-900 p-4 sm:grid-cols-2 xl:grid-cols-[180px_180px_210px_160px_auto]">
            <label>
                <span class="mb-1 block text-xs text-slate-500">{{ __('From') }}</span>
                <input type="date" name="date_from" value="{{ $filters['date_from'] }}" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5">
            </label>
            <label>
                <span class="mb-1 block text-xs text-slate-500">{{ __('To') }}</span>
                <input type="date" name="date_to" value="{{ $filters['date_to'] }}" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5">
            </label>
            <label>
                <span class="mb-1 block text-xs text-slate-500">{{ __('Metric') }}</span>
                <select name="metric" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5">
                    @foreach($metrics as $metric)
                        <option value="{{ $metric }}" @selected($filters['metric'] === $metric)>{{ $metricLabels[$metric] ?? __(str($metric)->title()->toString()) }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span class="mb-1 block text-xs text-slate-500">{{ __('Currency') }}</span>
                <select name="currency" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5" {{ $moneyMetric ? '' : 'disabled' }}>
                    @forelse($heatMap['currencies'] as $currency)
                        <option value="{{ $currency }}" @selected($filters['currency'] === $currency)>{{ $currency }}</option>
                    @empty
                        <option value="AFN">AFN</option>
                    @endforelse
                </select>
            </label>
            <button class="self-end rounded-xl bg-indigo-500 px-4 py-2.5 text-sm font-semibold hover:bg-indigo-400">{{ __('Apply') }}</button>
        </form>

        <div class="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4 2xl:grid-cols-8">
            <div class="rounded-2xl border border-white/10 bg-slate-900 p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('Coverage') }}</p>
                <p class="mt-2 text-2xl font-bold">{{ number_format($summary['coverage_percent'], 1) }}%</p>
                <p class="mt-1 text-xs text-slate-500">{{ $summary['visited_customers'] }}/{{ $summary['customers'] }} {{ __('customers visited') }}</p>
            </div>
            <div class="rounded-2xl border {{ $summary['stale_percent'] >= $heatMap['stale_attention_percent'] ? 'border-amber-400/20' : 'border-white/10' }} bg-slate-900 p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('Stale customers') }}</p>
                <p class="mt-2 text-2xl font-bold">{{ number_format($summary['stale_percent'], 1) }}%</p>
                <p class="mt-1 text-xs text-slate-500">{{ $summary['stale_customers'] }} {{ __('older than') }} {{ $heatMap['stale_customer_days'] }} {{ __('days') }}</p>
            </div>
            <div class="rounded-2xl border border-white/10 bg-slate-900 p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('Completed visits') }}</p>
                <p class="mt-2 text-2xl font-bold">{{ number_format($summary['visits']) }}</p>
            </div>
            <div class="rounded-2xl border border-white/10 bg-slate-900 p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('Approved sales') }}</p>
                <p class="mt-2 text-xl font-bold">{{ $summary['currency'] }} {{ number_format($summary['sales'], 2) }}</p>
            </div>
            <div class="rounded-2xl border border-white/10 bg-slate-900 p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('Verified collections') }}</p>
                <p class="mt-2 text-xl font-bold">{{ $summary['currency'] }} {{ number_format($summary['collections'], 2) }}</p>
            </div>
            <div class="rounded-2xl border {{ $summary['unassigned_customers'] > 0 ? 'border-amber-400/20' : 'border-white/10' }} bg-slate-900 p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('No territory') }}</p>
                <p class="mt-2 text-2xl font-bold">{{ $summary['unassigned_customers'] }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ __('active customers') }}</p>
            </div>
            <div class="rounded-2xl border {{ $summary['outside_polygon_customers'] > 0 ? 'border-rose-400/20' : 'border-white/10' }} bg-slate-900 p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('GPS mismatch') }}</p>
                <p class="mt-2 text-2xl font-bold">{{ $summary['outside_polygon_customers'] }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ __('outside assigned polygon') }}</p>
            </div>
            <div class="rounded-2xl border {{ $summary['territories_needing_attention'] > 0 ? 'border-amber-400/20' : 'border-white/10' }} bg-slate-900 p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('Territories to review') }}</p>
                <p class="mt-2 text-2xl font-bold">{{ $summary['territories_needing_attention'] }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ $summary['territories'] }} {{ __('total') }}</p>
            </div>
        </div>

        <div class="grid gap-5 2xl:grid-cols-[minmax(0,1fr)_400px]">
            <section class="overflow-hidden rounded-2xl border border-white/10 bg-slate-900">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/10 px-4 py-3">
                    <div>
                        <h2 class="font-semibold">{{ $metricLabels[$filters['metric']] ?? __(str($filters['metric'])->title()->toString()) }} {{ __('map') }}</h2>
                        <p class="mt-1 text-xs text-slate-500">{{ $filters['date_from'] }} → {{ $filters['date_to'] }} @if($moneyMetric) · {{ $filters['currency'] }} @endif</p>
                    </div>
                    <div class="text-xs text-slate-500">{{ __('Click a territory or customer for details.') }}</div>
                </div>
                <div id="territory-heat-map" class="h-[680px] bg-slate-950"></div>
            </section>

            <aside class="space-y-5">
                <section class="overflow-hidden rounded-2xl border border-white/10 bg-slate-900">
                    <div class="border-b border-white/10 px-4 py-3">
                        <h2 class="font-semibold">{{ __('Territory attention') }}</h2>
                        <p class="mt-1 text-xs leading-5 text-slate-500">{{ __('Coverage threshold:') }} {{ $heatMap['under_covered_threshold_percent'] }}% · {{ __('Stale threshold:') }} {{ $heatMap['stale_attention_percent'] }}% {{ __('after') }} {{ $heatMap['stale_customer_days'] }} {{ __('days') }}</p>
                    </div>
                    <div class="max-h-[360px] divide-y divide-white/10 overflow-y-auto">
                        @forelse($heatMap['attention'] as $row)
                            <div class="p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold">{{ $row['code'] }} — {{ $row['name'] }}</p>
                                        <div class="mt-2 flex flex-wrap gap-1.5">
                                            @foreach($row['attention_reasons'] as $reason)
                                                <span class="rounded-full bg-amber-500/10 px-2 py-1 text-[10px] font-semibold text-amber-300">{{ $attentionReasonLabels[$reason] ?? __(str($reason)->replace('_', ' ')->title()->toString()) }}</span>
                                            @endforeach
                                        </div>
                                    </div>
                                    <div class="text-end text-xs text-slate-400">
                                        <div>{{ number_format($row['coverage_percent'], 1) }}% {{ __('coverage') }}</div>
                                        <div class="mt-1">{{ number_format($row['stale_percent'], 1) }}% {{ __('stale') }}</div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="p-6 text-sm text-slate-500">{{ __('No territory currently crosses the configured attention thresholds.') }}</div>
                        @endforelse
                    </div>
                </section>

                <section class="rounded-2xl border border-white/10 bg-slate-900 p-4">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="font-semibold">{{ __('Geographic data quality') }}</h2>
                        <span class="rounded-full px-2.5 py-1 text-[10px] font-semibold {{ $heatMap['geometry_audit_enabled'] ? 'bg-emerald-500/10 text-emerald-300' : 'bg-slate-700/50 text-slate-400' }}">{{ $heatMap['geometry_audit_enabled'] ? __('Audit on') : __('Audit off') }}</span>
                    </div>
                    <div class="mt-3 grid grid-cols-2 gap-3">
                        <div class="rounded-xl bg-slate-950/70 p-3"><p class="text-[10px] uppercase tracking-wide text-slate-500">{{ __('Unassigned') }}</p><p class="mt-1 text-xl font-bold">{{ $summary['unassigned_customers'] }}</p></div>
                        <div class="rounded-xl bg-slate-950/70 p-3"><p class="text-[10px] uppercase tracking-wide text-slate-500">{{ __('Outside polygon') }}</p><p class="mt-1 text-xl font-bold">{{ $summary['outside_polygon_customers'] }}</p></div>
                        <div class="rounded-xl bg-slate-950/70 p-3"><p class="text-[10px] uppercase tracking-wide text-slate-500">{{ __('Missing GPS') }}</p><p class="mt-1 text-xl font-bold">{{ $summary['unmapped_customers'] }}</p></div>
                        <div class="rounded-xl bg-slate-950/70 p-3"><p class="text-[10px] uppercase tracking-wide text-slate-500">{{ __('Mapped') }}</p><p class="mt-1 text-xl font-bold">{{ $summary['mapped_customers'] }}</p></div>
                    </div>
                    @if($heatMap['geometry_audit_enabled'] && ($summary['unassigned_customers'] > 0 || $summary['outside_polygon_customers'] > 0))
                        <p class="mt-3 text-xs leading-5 text-slate-500">{{ __('Review customer master data before using territory coverage for operational decisions. GPS mismatches may indicate an incorrect territory assignment or inaccurate stored coordinates.') }}</p>
                    @endif
                </section>

                <section class="rounded-2xl border border-white/10 bg-slate-900 p-4">
                    <h2 class="font-semibold">{{ __('How FieldPulse reads this') }}</h2>
                    <ul class="mt-3 space-y-2 text-xs leading-5 text-slate-400">
                        <li>{{ __('Coverage counts each customer once when at least one completed visit exists in the selected period.') }}</li>
                        <li>{{ __('Stale status uses the most recent completed visit up to the selected end date, not only visits inside the displayed period.') }}</li>
                        <li>{{ __('Customer density uses polygon area and is reported as customers per square kilometer when a valid polygon is available.') }}</li>
                        <li>{{ __('Sales and collections never combine currencies; select one currency before comparing money values.') }}</li>
                    </ul>
                </section>
            </aside>
        </div>

        @if($summary['unassigned_customers'] > 0 || $summary['outside_polygon_customers'] > 0 || $summary['stale_customers'] > 0)
            <div class="mt-5 grid gap-5 xl:grid-cols-3">
                <section class="overflow-hidden rounded-2xl border border-white/10 bg-slate-900">
                    <div class="border-b border-white/10 px-4 py-3"><h2 class="font-semibold">{{ __('Customers without a territory') }}</h2><p class="mt-1 text-xs text-slate-500">{{ __('Showing up to 25 customer records you are allowed to view.') }}</p></div>
                    <div class="max-h-72 divide-y divide-white/10 overflow-y-auto">
                        @forelse($heatMap['unassigned_customers'] as $customer)
                            <div class="px-4 py-3"><p class="text-sm font-medium">{{ $customer['code'] }} — {{ $customer['name'] }}</p><p class="mt-1 text-xs text-slate-500">{{ $customer['latitude'] !== null ? number_format($customer['latitude'], 5).', '.number_format($customer['longitude'], 5) : __('No GPS coordinates') }}</p></div>
                        @empty
                            <div class="p-5 text-sm text-slate-500">{{ __('No visible customer details to show.') }}</div>
                        @endforelse
                    </div>
                </section>
                <section class="overflow-hidden rounded-2xl border border-white/10 bg-slate-900">
                    <div class="border-b border-white/10 px-4 py-3"><h2 class="font-semibold">{{ __('Customers outside assigned polygon') }}</h2><p class="mt-1 text-xs text-slate-500">{{ __('These GPS coordinates do not fall inside the currently assigned territory polygon.') }}</p></div>
                    <div class="max-h-72 divide-y divide-white/10 overflow-y-auto">
                        @forelse($heatMap['outside_polygon_customers'] as $customer)
                            <div class="px-4 py-3"><p class="text-sm font-medium">{{ $customer['code'] }} — {{ $customer['name'] }}</p><p class="mt-1 text-xs text-slate-500">{{ $customer['territory'] ?: __('No territory') }} · {{ number_format($customer['latitude'], 5) }}, {{ number_format($customer['longitude'], 5) }}</p></div>
                        @empty
                            <div class="p-5 text-sm text-slate-500">{{ __('No visible customer details to show.') }}</div>
                        @endforelse
                    </div>
                </section>
                <section class="overflow-hidden rounded-2xl border border-white/10 bg-slate-900">
                    <div class="border-b border-white/10 px-4 py-3"><h2 class="font-semibold">{{ __('Stale customers') }}</h2><p class="mt-1 text-xs text-slate-500">{{ __('No completed visit within the configured stale-customer age.') }}</p></div>
                    <div class="max-h-72 divide-y divide-white/10 overflow-y-auto">
                        @forelse($heatMap['stale_customers'] as $customer)
                            <div class="px-4 py-3"><p class="text-sm font-medium">{{ $customer['code'] }} — {{ $customer['name'] }}</p><p class="mt-1 text-xs text-slate-500">{{ $customer['territory'] ?: __('No territory') }} · {{ $customer['last_visited_at'] ? __('Last visit').' '.\Carbon\CarbonImmutable::parse($customer['last_visited_at'])->setTimezone($heatMap['timezone'])->format('Y-m-d') : __('Never visited') }}</p></div>
                        @empty
                            <div class="p-5 text-sm text-slate-500">{{ __('No visible customer details to show.') }}</div>
                        @endforelse
                    </div>
                </section>
            </div>
        @endif

        <section class="mt-5 overflow-hidden rounded-2xl border border-white/10 bg-slate-900">
            <div class="border-b border-white/10 px-4 py-3">
                <h2 class="font-semibold">{{ __('Territory comparison') }}</h2>
                <p class="mt-1 text-xs text-slate-500">{{ __('Operational comparison for the selected period. Density is only available when a valid polygon can be measured.') }}</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-[1100px] w-full text-left text-sm">
                    <thead class="bg-white/5 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3">{{ __('Territory') }}</th>
                            <th class="px-4 py-3">{{ __('Customers') }}</th>
                            <th class="px-4 py-3">{{ __('Coverage') }}</th>
                            <th class="px-4 py-3">{{ __('Stale') }}</th>
                            <th class="px-4 py-3">{{ __('Density') }}</th>
                            <th class="px-4 py-3">{{ __('Visits') }}</th>
                            <th class="px-4 py-3">{{ __('Sales') }}</th>
                            <th class="px-4 py-3">{{ __('Collections') }}</th>
                            <th class="px-4 py-3">{{ __('GPS issues') }}</th>
                            <th class="px-4 py-3">{{ __('Attention') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/10">
                        @forelse($heatMap['territories'] as $row)
                            <tr>
                                <td class="px-4 py-3"><p class="font-semibold">{{ $row['code'] }} — {{ $row['name'] }}</p>@if($row['area_km2'] !== null)<p class="mt-1 text-xs text-slate-500">{{ number_format($row['area_km2'], 2) }} km²</p>@endif</td>
                                <td class="px-4 py-3">{{ $row['customers'] }}</td>
                                <td class="px-4 py-3">{{ number_format($row['coverage_percent'], 1) }}%</td>
                                <td class="px-4 py-3">{{ $row['stale_customers'] }} <span class="text-xs text-slate-500">({{ number_format($row['stale_percent'], 1) }}%)</span></td>
                                <td class="px-4 py-3">{{ $row['customer_density_per_km2'] === null ? '—' : number_format($row['customer_density_per_km2'], 2).' /km²' }}</td>
                                <td class="px-4 py-3">{{ $row['visits'] }} <span class="text-xs text-slate-500">({{ number_format($row['visits_per_customer'], 2) }}/{{ __('customer') }})</span></td>
                                <td class="px-4 py-3">{{ $row['currency'] }} {{ number_format($row['sales'], 2) }}</td>
                                <td class="px-4 py-3">{{ $row['currency'] }} {{ number_format($row['collections'], 2) }}</td>
                                <td class="px-4 py-3">{{ $row['outside_polygon_customers'] + $row['unmapped_customers'] }}</td>
                                <td class="px-4 py-3">
                                    @if($row['attention_required'])
                                        <span class="rounded-full bg-amber-500/10 px-2.5 py-1 text-xs font-semibold text-amber-300">{{ count($row['attention_reasons']) }} {{ __('issue(s)') }}</span>
                                    @else
                                        <span class="rounded-full bg-emerald-500/10 px-2.5 py-1 text-xs font-semibold text-emerald-300">{{ __('Within thresholds') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="10" class="px-4 py-8 text-center text-slate-500">{{ __('No territory data is available for this period.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
        <script>
            (() => {
                const territories = @json($heatMap['territories']);
                const points = @json($heatMap['points']);
                const metric = @json($filters['metric']);
                const currency = @json($filters['currency']);
                const map = L.map('territory-heat-map').setView([34.5553, 69.2075], 10);

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; OpenStreetMap contributors',
                }).addTo(map);

                const bounds = [];

                const metricText = (row) => {
                    if (metric === 'coverage') {
                        return row.coverage_percent != null
                            ? Number(row.coverage_percent).toFixed(1) + '% coverage'
                            : (row.visited ? 'Visited in selected period' : 'Not visited in selected period');
                    }
                    if (metric === 'visits') return Number(row.visits || 0).toLocaleString() + ' visits';
                    if (metric === 'density') {
                        return row.customer_density_per_km2 != null
                            ? Number(row.customer_density_per_km2).toFixed(2) + ' customers/km²'
                            : 'Density unavailable';
                    }
                    if (metric === 'stale') {
                        return row.stale_percent != null
                            ? Number(row.stale_percent).toFixed(1) + '% stale'
                            : (row.stale ? 'Stale customer' : 'Recently visited');
                    }
                    if (metric === 'sales') return currency + ' ' + Number(row.sales || 0).toLocaleString(undefined, {maximumFractionDigits: 2});
                    return currency + ' ' + Number(row.collections || 0).toLocaleString(undefined, {maximumFractionDigits: 2});
                };

                const territoryColor = (row) => {
                    const intensity = Math.max(0, Math.min(1, Number(row.intensity || 0)));
                    if (metric === 'coverage') return `hsl(${5 + intensity * 120} 72% 48%)`;
                    if (metric === 'stale') return `hsl(${120 - intensity * 115} 72% 48%)`;
                    return `hsl(${215 - intensity * 35} ${55 + intensity * 25}% ${54 - intensity * 12}%)`;
                };

                const polygonLayer = (row) => {
                    const polygon = row.polygon;
                    if (!polygon) return null;
                    const style = {
                        color: territoryColor(row),
                        weight: row.attention_required ? 3 : 2,
                        fillColor: territoryColor(row),
                        fillOpacity: .18 + Number(row.intensity || 0) * .42,
                    };

                    try {
                        if (polygon.type === 'Feature' || polygon.type === 'FeatureCollection' || polygon.type === 'Polygon' || polygon.type === 'MultiPolygon') {
                            return L.geoJSON(polygon, {style}).addTo(map);
                        }
                        if (Array.isArray(polygon)) return L.polygon(polygon, style).addTo(map);
                    } catch (_) {}

                    return null;
                };

                for (const row of territories) {
                    const layer = polygonLayer(row);
                    if (!layer) continue;
                    const reasons = (row.attention_reasons || []).map((reason) => reason.replaceAll('_', ' ')).join(', ');
                    layer.bindPopup(
                        `<strong>${row.name}</strong><br>${metricText(row)}<br>${row.visited_customers}/${row.customers} customers visited<br>${row.stale_customers} stale customer(s)<br>${row.outside_polygon_customers} GPS mismatch(es)${reasons ? '<br><em>' + reasons + '</em>' : ''}`
                    );
                    try {
                        const layerBounds = layer.getBounds();
                        bounds.push(layerBounds.getNorthEast(), layerBounds.getSouthWest());
                    } catch (_) {}
                }

                for (const point of points) {
                    const intensity = Math.max(0, Math.min(1, Number(point.intensity || 0)));
                    let color;
                    if (metric === 'coverage') color = point.visited ? '#10b981' : '#f43f5e';
                    else if (metric === 'stale') color = point.stale ? '#f43f5e' : '#10b981';
                    else color = `hsl(${215 - intensity * 35} 75% ${52 - intensity * 10}%)`;

                    if (point.outside_polygon) color = '#fb7185';

                    const marker = L.circleMarker([point.latitude, point.longitude], {
                        radius: 5 + intensity * 13,
                        color,
                        weight: point.outside_polygon ? 3 : 2,
                        fillColor: color,
                        fillOpacity: .3 + intensity * .55,
                    }).addTo(map);

                    marker.bindPopup(
                        `<strong>${point.name}</strong><br>${point.code}<br>${metricText(point)}<br>${point.visits} completed visit(s)${point.outside_polygon ? '<br><strong>GPS outside assigned territory</strong>' : ''}`
                    );
                    bounds.push([point.latitude, point.longitude]);
                }

                if (bounds.length > 0) {
                    map.fitBounds(bounds, {padding: [30, 30], maxZoom: 15});
                }
            })();
        </script>
    @endif
</x-layouts.app>
