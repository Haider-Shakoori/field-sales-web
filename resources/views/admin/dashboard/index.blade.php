<x-layouts.app>
    <link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">

    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold">{{ $variant['title'] }}</h1>
            <p class="mt-1 text-sm text-slate-400">{{ $variant['subtitle'] }}</p>
            <p class="mt-1 text-xs text-slate-500">
                {{ $summary['local_date'] }} · {{ $summary['timezone'] }}
            </p>
        </div>
        <div class="rounded-xl border border-white/10 bg-slate-900 px-4 py-3 text-sm text-slate-300">
            Pending approvals:
            <span class="font-semibold">{{ $summary['orders']['pending_count'] + $summary['collections']['pending_count'] + $summary['expenses']['pending_count'] }}</span>
        </div>
    </div>

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-white/10 bg-slate-900 p-5">
            <div class="text-sm text-slate-400">Salesmen</div>
            <div class="mt-2 text-3xl font-bold">{{ $summary['salesmen']['total'] }}</div>
            <div class="mt-3 flex flex-wrap gap-2 text-xs">
                <span class="rounded-full bg-emerald-500/15 px-2.5 py-1 text-emerald-300">Online {{ $summary['salesmen']['live'] }}</span>
                <span class="rounded-full bg-amber-500/15 px-2.5 py-1 text-amber-300">Idle {{ $summary['salesmen']['stale'] }}</span>
                <span class="rounded-full bg-slate-700 px-2.5 py-1 text-slate-300">Offline {{ $summary['salesmen']['offline'] }}</span>
            </div>
        </div>

        <div class="rounded-2xl border border-white/10 bg-slate-900 p-5">
            <div class="text-sm text-slate-400">Visits today</div>
            <div class="mt-2 text-3xl font-bold">{{ $summary['visits']['completed'] }}</div>
            <div class="mt-3 text-xs text-slate-400">Active now: {{ $summary['visits']['active'] }}</div>
        </div>

        <div class="rounded-2xl border border-white/10 bg-slate-900 p-5">
            <div class="text-sm text-slate-400">Approved orders today</div>
            <div class="mt-2 text-3xl font-bold">{{ $summary['orders']['approved_count'] }}</div>
            <div class="mt-3 space-y-1 text-xs text-slate-300">
                @forelse($summary['orders']['totals'] as $total)
                    <div>{{ number_format($total['total'], 2) }} {{ $total['currency'] }}</div>
                @empty
                    <div class="text-slate-500">No approved sales yet</div>
                @endforelse
            </div>
        </div>

        <div class="rounded-2xl border border-white/10 bg-slate-900 p-5">
            <div class="text-sm text-slate-400">Verified collections today</div>
            <div class="mt-2 text-3xl font-bold">{{ $summary['collections']['verified_count'] }}</div>
            <div class="mt-3 space-y-1 text-xs text-slate-300">
                @forelse($summary['collections']['totals'] as $total)
                    <div>{{ number_format($total['total'], 2) }} {{ $total['currency'] }}</div>
                @empty
                    <div class="text-slate-500">No verified collections yet</div>
                @endforelse
            </div>
        </div>
    </section>

    <section class="mt-6 grid gap-4 lg:grid-cols-3">
        <div class="rounded-2xl border border-white/10 bg-slate-900 p-5">
            <div class="text-sm text-slate-400">Approved expenses today</div>
            <div class="mt-2 text-2xl font-bold">{{ $summary['expenses']['approved_count'] }}</div>
            <div class="mt-3 space-y-1 text-sm text-slate-300">
                @forelse($summary['expenses']['totals'] as $total)
                    <div>{{ number_format($total['total'], 2) }} {{ $total['currency'] }}</div>
                @empty
                    <div class="text-slate-500">No approved expenses yet</div>
                @endforelse
            </div>
        </div>

        <div class="rounded-2xl border border-white/10 bg-slate-900 p-5">
            <div class="text-sm text-slate-400">On duty</div>
            <div class="mt-2 text-2xl font-bold">{{ $summary['salesmen']['on_duty'] }}</div>
            <p class="mt-3 text-sm text-slate-400">Salesmen with an active work session.</p>
        </div>

        <div class="rounded-2xl border border-white/10 bg-slate-900 p-5">
            <div class="text-sm text-slate-400">Review queue</div>
            <div class="mt-3 grid grid-cols-3 gap-3 text-center">
                <div class="rounded-xl bg-white/5 p-3">
                    <div class="text-xl font-bold">{{ $summary['orders']['pending_count'] }}</div>
                    <div class="mt-1 text-xs text-slate-400">Orders</div>
                </div>
                <div class="rounded-xl bg-white/5 p-3">
                    <div class="text-xl font-bold">{{ $summary['collections']['pending_count'] }}</div>
                    <div class="mt-1 text-xs text-slate-400">Collections</div>
                </div>
                <div class="rounded-xl bg-white/5 p-3">
                    <div class="text-xl font-bold">{{ $summary['expenses']['pending_count'] }}</div>
                    <div class="mt-1 text-xs text-slate-400">Expenses</div>
                </div>
            </div>
        </div>
    </section>

    @if($visibility['orders'] || $visibility['visits'])
        <section class="mt-6 grid gap-4 xl:grid-cols-2">
            @if($visibility['orders'])
                <div class="rounded-2xl border border-white/10 bg-slate-900 p-5">
                    <div class="mb-4">
                        <h2 class="font-semibold">Sales trend</h2>
                        <p class="mt-1 text-xs text-slate-400">Approved order value for the last {{ $analytics['days'] }} days, separated by currency.</p>
                    </div>
                    <div class="h-72">
                        <canvas id="sales-trend-chart" aria-label="Sales trend chart" role="img"></canvas>
                    </div>
                </div>
            @endif

            @if($visibility['visits'])
                <div class="rounded-2xl border border-white/10 bg-slate-900 p-5">
                    <div class="mb-4">
                        <h2 class="font-semibold">Visit completion</h2>
                        <p class="mt-1 text-xs text-slate-400">Started versus completed visits for the last {{ $analytics['days'] }} days.</p>
                    </div>
                    <div class="h-72">
                        <canvas id="visit-completion-chart" aria-label="Visit completion chart" role="img"></canvas>
                    </div>
                </div>
            @endif
        </section>
    @endif

    @if($visibility['tracking'])
        <section class="mt-6 overflow-hidden rounded-2xl border border-white/10 bg-slate-900">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/10 px-5 py-4">
                <div>
                    <h2 class="font-semibold">Live salesman map</h2>
                    <p class="mt-1 text-xs text-slate-400">Live positions with today's traveled route from the work-session start point.</p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <a href="{{ route('admin.live-map') }}" class="rounded-lg bg-white/10 px-3 py-2 text-xs text-slate-200 hover:bg-white/20">Open live map</a>
                    <button id="route-toggle" type="button" class="rounded-lg bg-white/10 px-3 py-2 text-xs text-slate-200 hover:bg-white/20">Hide routes</button>
                    <div id="map-status" class="text-xs text-slate-400">Loading locations…</div>
                </div>
            </div>
            <div id="live-map" class="h-[520px] bg-slate-950"></div>
            <div class="grid gap-2 border-t border-white/10 px-5 py-3 text-xs text-slate-400 sm:grid-cols-4">
                <div><span class="font-semibold text-emerald-300">Online:</span> updated within 5 minutes</div>
                <div><span class="font-semibold text-amber-300">Idle:</span> 5–30 minutes old</div>
                <div><span class="font-semibold text-slate-300">Offline:</span> older than 30 minutes or no position</div>
                <div><span class="font-semibold text-indigo-300">Route:</span> start → latest position</div>
            </div>
        </section>

        <section class="mt-6 overflow-hidden rounded-2xl border border-white/10 bg-slate-900">
            <div class="border-b border-white/10 px-5 py-4">
                <h2 class="font-semibold">Salesman status</h2>
                <p class="mt-1 text-xs text-slate-400">Current field availability and latest reported device position.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-white/5 text-xs uppercase tracking-wide text-slate-400">
                        <tr>
                            <th class="px-5 py-3">Salesman</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">On duty</th>
                            <th class="px-5 py-3">Last update</th>
                            <th class="px-5 py-3">Location</th>
                            <th class="px-5 py-3">Battery</th>
                            <th class="px-5 py-3">Distance</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody id="salesman-status-body" class="divide-y divide-white/10">
                        @forelse($initialLocations as $item)
                            <tr>
                                <td class="px-5 py-4">
                                    <div class="font-medium">{{ $item['salesman_name'] }}</div>
                                    <div class="text-xs text-slate-500">{{ $item['employee_code'] }}</div>
                                </td>
                                <td class="px-5 py-4">{{ str($item['status'])->title() }}</td>
                                <td class="px-5 py-4">{{ $item['on_duty'] ? 'Yes' : 'No' }}</td>
                                <td class="px-5 py-4 text-slate-400">{{ $item['location']['recorded_at'] ?? '—' }}</td>
                                <td class="px-5 py-4 text-slate-400">
                                    @if($item['location'])
                                        {{ number_format($item['location']['latitude'], 5) }}, {{ number_format($item['location']['longitude'], 5) }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-5 py-4 text-slate-400">
                                    {{ isset($item['location']['battery_level']) && $item['location']['battery_level'] !== null ? $item['location']['battery_level'].'%' : '—' }}
                                </td>
                                <td class="px-5 py-4 text-slate-400">—</td>
                                <td class="px-5 py-4 text-right text-slate-500">—</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-5 py-8 text-center text-slate-400">No salesmen are currently visible.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    <section class="mt-6 rounded-2xl border border-white/10 bg-slate-900">
        <div class="border-b border-white/10 px-5 py-4">
            <h2 class="font-semibold">Recent activity</h2>
        </div>
        <div class="divide-y divide-white/10">
            @forelse($recentActivity as $item)
                <div class="flex flex-wrap items-center gap-4 px-5 py-4">
                    <div class="min-w-24 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $item['type'] }}</div>
                    <div class="min-w-0 flex-1">
                        <div class="font-medium">{{ $item['title'] }}</div>
                        <div class="mt-1 text-sm text-slate-400">{{ $item['subtitle'] }}</div>
                    </div>
                    <div class="text-right">
                        <div class="text-sm">{{ str($item['status'])->replace('_', ' ')->title() }}</div>
                        <div class="mt-1 text-xs text-slate-500">
                            {{ \Carbon\CarbonImmutable::parse($item['at'])->setTimezone($summary['timezone'])->format('Y-m-d H:i') }}
                        </div>
                    </div>
                </div>
            @empty
                <div class="px-5 py-10 text-center text-slate-400">No recent operational activity.</div>
            @endforelse
        </div>
    </section>

    @if($visibility['orders'] || $visibility['visits'])
        <script src="{{ asset('vendor/chartjs/chart.umd.min.js') }}"></script>
        <script>
            (() => {
                const labels = @json($analytics['labels']);
                const commonOptions = {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {mode: 'index', intersect: false},
                    plugins: {
                        legend: {labels: {color: '#cbd5e1'}},
                    },
                    scales: {
                        x: {ticks: {color: '#94a3b8'}, grid: {color: 'rgba(148,163,184,.08)'}},
                        y: {beginAtZero: true, ticks: {color: '#94a3b8'}, grid: {color: 'rgba(148,163,184,.08)'}},
                    },
                };

                const palette = ['#38bdf8', '#a78bfa', '#34d399', '#f59e0b', '#fb7185', '#22d3ee'];

                const salesCanvas = document.getElementById('sales-trend-chart');
                if (salesCanvas) {
                    const sales = @json($analytics['sales']['datasets']);
                    new Chart(salesCanvas, {
                        type: 'line',
                        data: {
                            labels,
                            datasets: sales.map((dataset, index) => ({
                                label: dataset.currency,
                                data: dataset.values,
                                borderColor: palette[index % palette.length],
                                backgroundColor: palette[index % palette.length],
                                tension: .3,
                                pointRadius: 3,
                            })),
                        },
                        options: commonOptions,
                    });
                }

                const visitsCanvas = document.getElementById('visit-completion-chart');
                if (visitsCanvas) {
                    new Chart(visitsCanvas, {
                        type: 'bar',
                        data: {
                            labels,
                            datasets: [
                                {
                                    label: 'Started',
                                    data: @json($analytics['visits']['started']),
                                    backgroundColor: '#64748b',
                                },
                                {
                                    label: 'Completed',
                                    data: @json($analytics['visits']['completed']),
                                    backgroundColor: '#34d399',
                                },
                            ],
                        },
                        options: commonOptions,
                    });
                }
            })();
        </script>
    @endif

    @if($visibility['tracking'])
        <script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
        <script>
            (() => {
                const endpoint = @json(route('admin.dashboard.live-locations'));
                const endpointWithTracks = endpoint + (endpoint.includes('?') ? '&' : '?') + 'include_tracks=1';
                const status = document.getElementById('map-status');
                const tableBody = document.getElementById('salesman-status-body');
                const routeToggle = document.getElementById('route-toggle');
                const map = L.map('live-map', {
                    zoomControl: true,
                    attributionControl: true,
                }).setView([34.5553, 69.2075], 11);

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; OpenStreetMap contributors',
                }).addTo(map);

                const palette = ['#6366f1', '#0ea5e9', '#14b8a6', '#f59e0b', '#ec4899', '#8b5cf6', '#22c55e', '#f97316'];
                const markers = new Map();
                const routes = new Map();
                const routeLayer = L.layerGroup().addTo(map);
                let routesVisible = true;
                let hasFitted = false;
                let includeTracks = true;
                let routeColorIndex = 0;

                const markerClass = (freshness) => {
                    if (freshness === 'live') return 'background:#10b981;';
                    if (freshness === 'stale') return 'background:#f59e0b;';
                    return 'background:#64748b;';
                };

                const routeFor = (salesmanId) => {
                    if (!routes.has(salesmanId)) {
                        routes.set(salesmanId, {
                            color: palette[routeColorIndex++ % palette.length],
                            line: null,
                            start: null,
                            lastRecordedAt: null,
                            distanceKm: null,
                        });
                    }

                    return routes.get(salesmanId);
                };

                const setTrack = (item, bounds) => {
                    const track = item.track;

                    if (!track || !Array.isArray(track.points) || track.points.length < 2) {
                        return;
                    }

                    const latlngs = track.points.map((point) => [point[0], point[1]]);
                    const entry = routeFor(item.salesman_id);

                    if (!entry.line) {
                        entry.line = L.polyline(latlngs, {
                            color: entry.color,
                            weight: 4,
                            opacity: .85,
                            lineJoin: 'round',
                        }).addTo(routeLayer);
                        entry.start = L.circleMarker(latlngs[0], {
                            radius: 6,
                            color: '#ffffff',
                            weight: 2,
                            fillColor: entry.color,
                            fillOpacity: 1,
                        }).addTo(routeLayer);
                        entry.start.bindTooltip(item.salesman_name + ' · start');
                    } else {
                        entry.line.setLatLngs(latlngs);
                        entry.start.setLatLng(latlngs[0]);
                    }

                    entry.lastRecordedAt = track.points[track.points.length - 1][2];
                    entry.distanceKm = track.distance_km;
                    entry.line.bindTooltip(item.salesman_name + ' · ' + Number(track.distance_km).toFixed(1) + ' km today');
                    entry.line.bindPopup(popupFor(item, track));

                    for (const latlng of latlngs) {
                        bounds.push(latlng);
                    }
                };

                const appendPoint = (item) => {
                    const entry = routes.get(item.salesman_id);

                    if (!entry || !entry.line || !item.location) {
                        return;
                    }

                    if (entry.lastRecordedAt === item.location.recorded_at) {
                        return;
                    }

                    entry.line.addLatLng([item.location.latitude, item.location.longitude]);
                    entry.lastRecordedAt = item.location.recorded_at;
                };

                const routeButton = (item) => {
                    const entry = routes.get(item.salesman_id);

                    if (!entry || !entry.line) {
                        const empty = document.createElement('span');
                        empty.className = 'text-xs text-slate-500';
                        empty.textContent = '—';
                        return empty;
                    }

                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'rounded-lg bg-white/10 px-3 py-1.5 text-xs text-slate-200 hover:bg-white/20';
                    button.textContent = 'View route';
                    button.addEventListener('click', () => {
                        map.fitBounds(entry.line.getBounds(), {padding: [40, 40], maxZoom: 16});
                    });

                    return button;
                };

                const renderStatusTable = (items) => {
                    tableBody.replaceChildren();

                    if (items.length === 0) {
                        const row = document.createElement('tr');
                        const cell = document.createElement('td');
                        cell.colSpan = 8;
                        cell.className = 'px-5 py-8 text-center text-slate-400';
                        cell.textContent = 'No salesmen are currently visible.';
                        row.appendChild(cell);
                        tableBody.appendChild(row);
                        return;
                    }

                    for (const item of items) {
                        const row = document.createElement('tr');
                        const route = routes.get(item.salesman_id);
                        const values = [
                            item.salesman_name + ' · ' + item.employee_code,
                            item.status.charAt(0).toUpperCase() + item.status.slice(1),
                            item.on_duty ? 'Yes' : 'No',
                            item.location?.recorded_at ?? '—',
                            item.location ? item.location.latitude.toFixed(5) + ', ' + item.location.longitude.toFixed(5) : '—',
                            item.location?.battery_level == null ? '—' : item.location.battery_level + '%',
                            route && route.distanceKm != null ? Number(route.distanceKm).toFixed(1) + ' km' : '—',
                        ];

                        for (const value of values) {
                            const cell = document.createElement('td');
                            cell.className = 'px-5 py-4 text-slate-300';
                            cell.textContent = value;
                            row.appendChild(cell);
                        }

                        const routeCell = document.createElement('td');
                        routeCell.className = 'px-5 py-4 text-right';
                        routeCell.appendChild(routeButton(item));
                        row.appendChild(routeCell);

                        tableBody.appendChild(row);
                    }
                };

                const iconFor = (freshness) => L.divIcon({
                    className: '',
                    html: '<div style="' + markerClass(freshness) + 'width:16px;height:16px;border:3px solid white;border-radius:9999px;box-shadow:0 1px 8px rgba(0,0,0,.45)"></div>',
                    iconSize: [16, 16],
                    iconAnchor: [8, 8],
                });

                const textLine = (label, value) => {
                    const row = document.createElement('div');
                    const strong = document.createElement('strong');
                    strong.textContent = label + ': ';
                    row.appendChild(strong);
                    row.appendChild(document.createTextNode(value ?? '—'));
                    return row;
                };

                const popupFor = (item, track = null) => {
                    const box = document.createElement('div');
                    box.style.minWidth = '220px';

                    const title = document.createElement('div');
                    title.style.fontWeight = '700';
                    title.style.marginBottom = '6px';
                    title.textContent = item.salesman_name;
                    box.appendChild(title);

                    box.appendChild(textLine('Status', item.status));
                    box.appendChild(textLine('On duty', item.on_duty ? 'Yes' : 'No'));

                    if (track) {
                        box.appendChild(textLine('Route distance', Number(track.distance_km).toFixed(1) + ' km'));
                        if (track.points.length > 0) {
                            box.appendChild(textLine('Started', track.points[0][2]));
                        }
                    }

                    if (item.location) {
                        box.appendChild(textLine('Recorded', item.location.recorded_at));
                        box.appendChild(textLine('Accuracy', item.location.accuracy + ' m'));
                        box.appendChild(textLine('Speed', item.location.speed == null ? '—' : item.location.speed + ' m/s'));
                        box.appendChild(textLine('Battery', item.location.battery_level == null ? '—' : item.location.battery_level + '%'));
                        box.appendChild(textLine('Network', item.location.network_status));
                        if (item.location.is_mock_location) {
                            const warning = document.createElement('div');
                            warning.style.color = '#b45309';
                            warning.style.fontWeight = '700';
                            warning.style.marginTop = '6px';
                            warning.textContent = 'Mock location reported';
                            box.appendChild(warning);
                        }
                    }

                    return box;
                };

                const applyRouteVisibility = () => {
                    if (routesVisible) {
                        if (!map.hasLayer(routeLayer)) {
                            map.addLayer(routeLayer);
                        }
                    } else if (map.hasLayer(routeLayer)) {
                        map.removeLayer(routeLayer);
                    }

                    routeToggle.textContent = routesVisible ? 'Hide routes' : 'Show routes';
                };

                routeToggle.addEventListener('click', () => {
                    routesVisible = !routesVisible;
                    applyRouteVisibility();
                });

                const refresh = async () => {
                    try {
                        const response = await fetch(includeTracks ? endpointWithTracks : endpoint, {
                            headers: {'Accept': 'application/json'},
                            credentials: 'same-origin',
                            cache: 'no-store',
                        });

                        if (!response.ok) {
                            throw new Error('Live location request failed.');
                        }

                        const payload = await response.json();
                        includeTracks = false;
                        const seen = new Set();
                        const bounds = [];

                        for (const item of payload.data) {
                            seen.add(item.salesman_id);
                            setTrack(item, bounds);
                            appendPoint(item);

                            const location = item.location;

                            if (!location) {
                                const existing = markers.get(item.salesman_id);
                                if (existing) {
                                    map.removeLayer(existing);
                                    markers.delete(item.salesman_id);
                                }
                                continue;
                            }

                            const point = [location.latitude, location.longitude];
                            bounds.push(point);

                            let marker = markers.get(item.salesman_id);
                            if (!marker) {
                                marker = L.marker(point, {icon: iconFor(item.freshness)}).addTo(map);
                                markers.set(item.salesman_id, marker);
                            } else {
                                marker.setLatLng(point);
                                marker.setIcon(iconFor(item.freshness));
                            }

                            marker.bindPopup(popupFor(item, item.track ?? routes.get(item.salesman_id) ?? null));
                            marker.bindTooltip(item.salesman_name);
                        }

                        for (const [salesmanId, marker] of markers.entries()) {
                            if (!seen.has(salesmanId)) {
                                map.removeLayer(marker);
                                markers.delete(salesmanId);

                                const route = routes.get(salesmanId);
                                if (route) {
                                    if (route.line) {
                                        routeLayer.removeLayer(route.line);
                                    }
                                    if (route.start) {
                                        routeLayer.removeLayer(route.start);
                                    }
                                    routes.delete(salesmanId);
                                }
                            }
                        }

                        if (!hasFitted && bounds.length > 0) {
                            map.fitBounds(bounds, {padding: [30, 30], maxZoom: 15});
                            hasFitted = true;
                        }

                        renderStatusTable(payload.data);

                        const online = payload.data.filter((item) => item.status === 'online').length;
                        const idle = payload.data.filter((item) => item.status === 'idle').length;
                        const offline = payload.data.filter((item) => item.status === 'offline').length;
                        status.textContent = 'Online ' + online + ' · Idle ' + idle + ' · Offline ' + offline
                            + ' · refreshed ' + new Date(payload.generated_at).toLocaleTimeString();
                    } catch (error) {
                        status.textContent = 'Live map refresh failed. Retrying automatically.';
                    }
                };

                applyRouteVisibility();
                refresh();
                window.setInterval(refresh, 30000);
            })();
        </script>
    @endif
</x-layouts.app>
