<x-layouts.app>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">

    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold">Operations dashboard</h1>
            <p class="mt-1 text-sm text-slate-400">
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
                <span class="rounded-full bg-emerald-500/15 px-2.5 py-1 text-emerald-300">Live {{ $summary['salesmen']['live'] }}</span>
                <span class="rounded-full bg-amber-500/15 px-2.5 py-1 text-amber-300">Stale {{ $summary['salesmen']['stale'] }}</span>
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

    @if($canTrack)
        <section class="mt-6 overflow-hidden rounded-2xl border border-white/10 bg-slate-900">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/10 px-5 py-4">
                <div>
                    <h2 class="font-semibold">Live salesman map</h2>
                    <p class="mt-1 text-xs text-slate-400">Latest current position only. No GPS history is loaded on this dashboard.</p>
                </div>
                <div id="map-status" class="text-xs text-slate-400">Loading locations…</div>
            </div>
            <div id="live-map" class="h-[520px] bg-slate-950"></div>
            <div class="grid gap-2 border-t border-white/10 px-5 py-3 text-xs text-slate-400 sm:grid-cols-3">
                <div><span class="font-semibold text-emerald-300">Live:</span> updated within 5 minutes</div>
                <div><span class="font-semibold text-amber-300">Stale:</span> 5–30 minutes old</div>
                <div><span class="font-semibold text-slate-300">Offline:</span> older than 30 minutes or no position</div>
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
                            {{ CarbonCarbonImmutable::parse($item['at'])->setTimezone($summary['timezone'])->format('Y-m-d H:i') }}
                        </div>
                    </div>
                </div>
            @empty
                <div class="px-5 py-10 text-center text-slate-400">No recent operational activity.</div>
            @endforelse
        </div>
    </section>

    @if($canTrack)
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
        <script>
            (() => {
                const endpoint = @json(route('admin.dashboard.live-locations'));
                const status = document.getElementById('map-status');
                const map = L.map('live-map', {
                    zoomControl: true,
                    attributionControl: true,
                }).setView([34.5553, 69.2075], 11);

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; OpenStreetMap contributors',
                }).addTo(map);

                const markers = new Map();
                let hasFitted = false;

                const markerClass = (freshness) => {
                    if (freshness === 'live') return 'background:#10b981;';
                    if (freshness === 'stale') return 'background:#f59e0b;';
                    return 'background:#64748b;';
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

                const popupFor = (item) => {
                    const box = document.createElement('div');
                    box.style.minWidth = '220px';

                    const title = document.createElement('div');
                    title.style.fontWeight = '700';
                    title.style.marginBottom = '6px';
                    title.textContent = item.salesman_name;
                    box.appendChild(title);

                    box.appendChild(textLine('Status', item.freshness));
                    box.appendChild(textLine('On duty', item.on_duty ? 'Yes' : 'No'));

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

                const refresh = async () => {
                    try {
                        const response = await fetch(endpoint, {
                            headers: {'Accept': 'application/json'},
                            credentials: 'same-origin',
                            cache: 'no-store',
                        });

                        if (!response.ok) {
                            throw new Error('Live location request failed.');
                        }

                        const payload = await response.json();
                        const seen = new Set();
                        const bounds = [];

                        for (const item of payload.data) {
                            seen.add(item.salesman_id);
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

                            marker.bindPopup(popupFor(item));
                            marker.bindTooltip(item.salesman_name);
                        }

                        for (const [salesmanId, marker] of markers.entries()) {
                            if (!seen.has(salesmanId)) {
                                map.removeLayer(marker);
                                markers.delete(salesmanId);
                            }
                        }

                        if (!hasFitted && bounds.length > 0) {
                            map.fitBounds(bounds, {padding: [30, 30], maxZoom: 15});
                            hasFitted = true;
                        }

                        const live = payload.data.filter((item) => item.freshness === 'live').length;
                        const stale = payload.data.filter((item) => item.freshness === 'stale').length;
                        const offline = payload.data.filter((item) => item.freshness === 'offline').length;
                        status.textContent = 'Live ' + live + ' · Stale ' + stale + ' · Offline ' + offline
                            + ' · refreshed ' + new Date(payload.generated_at).toLocaleTimeString();
                    } catch (error) {
                        status.textContent = 'Live map refresh failed. Retrying automatically.';
                    }
                };

                refresh();
                window.setInterval(refresh, 30000);
            })();
        </script>
    @endif
</x-layouts.app>
