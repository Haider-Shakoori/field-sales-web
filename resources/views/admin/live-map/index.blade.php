<x-layouts.app :fullscreen="true">
    <link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
    <style>
        .fp-live-map-shell {
            position: relative;
            width: 100vw;
            height: 100vh;
            height: 100dvh;
            overflow: hidden;
            background: #020617;
        }

        #live-map {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            background: #020617;
        }

        .fp-live-map-toolbar {
            position: absolute;
            inset-inline: 12px;
            top: 12px;
            z-index: 1100;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 10px 12px;
            border: 1px solid rgba(255, 255, 255, .1);
            border-radius: 18px;
            background: rgba(15, 23, 42, .9);
            box-shadow: 0 18px 45px rgba(2, 6, 23, .34);
            backdrop-filter: blur(18px);
        }

        .fp-live-map-panel {
            position: absolute;
            inset-inline-start: 12px;
            top: 88px;
            bottom: 12px;
            z-index: 1000;
            display: flex;
            width: min(360px, calc(100vw - 24px));
            flex-direction: column;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, .1);
            border-radius: 20px;
            background: rgba(15, 23, 42, .94);
            box-shadow: 0 22px 55px rgba(2, 6, 23, .42);
            backdrop-filter: blur(20px);
            transform: translateX(calc(-100% - 24px));
            transition: transform .22s ease;
        }

        html[dir="rtl"] .fp-live-map-panel {
            transform: translateX(calc(100% + 24px));
        }

        .fp-live-map-panel.is-open,
        html[dir="rtl"] .fp-live-map-panel.is-open {
            transform: translateX(0);
        }

        .fp-live-map-toolbar-actions {
            display: flex;
            min-width: 0;
            flex-wrap: wrap;
            align-items: center;
            justify-content: flex-end;
            gap: 8px;
        }

        .fp-live-map-control {
            display: inline-flex;
            min-height: 38px;
            align-items: center;
            justify-content: center;
            gap: 7px;
            border: 1px solid rgba(255, 255, 255, .08);
            border-radius: 11px;
            background: rgba(255, 255, 255, .08);
            padding: 8px 11px;
            color: #e2e8f0;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
            transition: background .15s ease, border-color .15s ease;
        }

        .fp-live-map-control:hover {
            border-color: rgba(129, 140, 248, .4);
            background: rgba(99, 102, 241, .18);
        }

        .fp-live-map-statuses {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 6px;
        }

        .fp-live-map-panel-list {
            min-height: 0;
            flex: 1;
            overflow-y: auto;
        }

        .fp-live-map-mobile-label {
            display: none;
        }

        .leaflet-control-zoom {
            border: 1px solid rgba(255,255,255,.12) !important;
            box-shadow: 0 12px 30px rgba(2,6,23,.25) !important;
        }

        @media (max-width: 767px) {
            .fp-live-map-toolbar {
                align-items: flex-start;
                padding: 9px;
            }

            .fp-live-map-toolbar-copy p {
                display: none;
            }

            .fp-live-map-toolbar-actions {
                flex-wrap: nowrap;
                overflow-x: auto;
                padding-bottom: 2px;
                scrollbar-width: none;
            }

            .fp-live-map-toolbar-actions::-webkit-scrollbar {
                display: none;
            }

            .fp-live-map-statuses {
                display: none;
            }

            .fp-live-map-panel {
                inset-inline: 8px;
                top: auto;
                bottom: 8px;
                width: auto;
                height: min(58vh, 560px);
                transform: translateY(calc(100% + 18px));
            }

            html[dir="rtl"] .fp-live-map-panel {
                transform: translateY(calc(100% + 18px));
            }

            .fp-live-map-panel.is-open,
            html[dir="rtl"] .fp-live-map-panel.is-open {
                transform: translateY(0);
            }

            .fp-live-map-mobile-label {
                display: inline;
            }

            .fp-live-map-desktop-label {
                display: none;
            }
        }

        @media (min-width: 768px) {
            .fp-live-map-panel {
                transform: translateX(0);
            }

            html[dir="rtl"] .fp-live-map-panel {
                transform: translateX(0);
            }

            .fp-live-map-panel:not(.is-open) {
                transform: translateX(calc(-100% - 24px));
            }

            html[dir="rtl"] .fp-live-map-panel:not(.is-open) {
                transform: translateX(calc(100% + 24px));
            }
        }

        html[data-theme="light"] .fp-live-map-toolbar,
        html[data-theme="light"] .fp-live-map-panel {
            border-color: rgba(148, 163, 184, .3);
            background: rgba(255, 255, 255, .93);
            box-shadow: 0 18px 45px rgba(15, 23, 42, .13);
        }

        html[data-theme="light"] .fp-live-map-control {
            border-color: #dbe4ee;
            background: #f1f5f9;
            color: #334155;
        }
    </style>

    <div class="fp-live-map-shell">
        <div id="live-map"></div>

        <header class="fp-live-map-toolbar">
            <div class="fp-live-map-toolbar-copy min-w-0">
                <div class="flex min-w-0 items-center gap-3">
                    <button type="button" onclick="history.back()" class="fp-live-map-control shrink-0" aria-label="{{ __('Go back') }}">
                        <svg class="fp-directional-icon h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6"/>
                        </svg>
                        <span class="fp-live-map-desktop-label">{{ __('Back') }}</span>
                    </button>
                    <div class="min-w-0">
                        <h1 class="truncate text-base font-bold sm:text-lg">{{ __('Live map') }}</h1>
                        <p id="map-status-text" class="mt-0.5 truncate text-xs text-slate-400">{{ __('Loading salesmen…') }}</p>
                    </div>
                </div>
            </div>

            <div class="fp-live-map-statuses text-xs">
                <span class="rounded-full bg-emerald-500/15 px-2.5 py-1 text-emerald-300">{{ __('Online') }} <span id="count-online">0</span></span>
                <span class="rounded-full bg-amber-500/15 px-2.5 py-1 text-amber-300">{{ __('Idle') }} <span id="count-idle">0</span></span>
                <span class="rounded-full bg-slate-700 px-2.5 py-1 text-slate-300">{{ __('Offline') }} <span id="count-offline">0</span></span>
            </div>

            <div class="fp-live-map-toolbar-actions">
                <button id="panel-toggle" type="button" class="fp-live-map-control" aria-expanded="true" aria-controls="salesman-panel">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path stroke-linecap="round" d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                    <span class="fp-live-map-desktop-label">{{ __('Salesmen') }}</span>
                    <span class="fp-live-map-mobile-label">{{ __('Team') }}</span>
                </button>
                <button id="fit-all" type="button" class="fp-live-map-control">{{ __('Fit all') }}</button>
                <button id="route-toggle" type="button" class="fp-live-map-control">{{ __('Hide routes') }}</button>
                <button id="fullscreen-menu-button" type="button" data-fullscreen-menu-open class="fp-live-map-control" aria-haspopup="dialog" aria-controls="fullscreen-nav-modal">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                    <span>{{ __('Menu') }}</span>
                </button>
            </div>
        </header>

        <aside id="salesman-panel" class="fp-live-map-panel is-open" aria-label="{{ __('Salesmen') }}">
            <div class="border-b border-white/10 p-4">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold">{{ __('Sales team') }}</p>
                        <p class="mt-0.5 text-xs text-slate-500">{{ __('Select a salesman to focus their location and route.') }}</p>
                    </div>
                    <button id="panel-close" type="button" class="fp-live-map-control !min-h-0 !px-2.5 !py-2 md:hidden" aria-label="{{ __('Close salesmen panel') }}">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/>
                        </svg>
                    </button>
                </div>

                <input id="map-search" placeholder="{{ __('Search salesman or code') }}" class="w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-2.5 text-sm">

                <div class="mt-3 flex flex-wrap items-center gap-3 text-sm">
                    <select id="map-status-filter" class="rounded-xl border border-white/10 bg-slate-950 px-3 py-2 text-sm">
                        <option value="all">{{ __('All statuses') }}</option>
                        <option value="online">{{ __('Online') }}</option>
                        <option value="idle">{{ __('Idle') }}</option>
                        <option value="offline">{{ __('Offline') }}</option>
                    </select>
                    <label class="flex items-center gap-2 text-slate-300">
                        <input id="map-on-duty" type="checkbox" class="h-4 w-4 rounded border-white/20 bg-slate-950">
                        {{ __('On duty only') }}
                    </label>
                </div>
            </div>
            <div id="salesman-list" class="fp-live-map-panel-list divide-y divide-white/5"></div>
        </aside>
    </div>

    <script>
        (() => {
            const panel = document.getElementById('salesman-panel');
            const toggle = document.getElementById('panel-toggle');
            const close = document.getElementById('panel-close');
            const desktop = window.matchMedia('(min-width: 768px)');

            const setOpen = (open) => {
                panel?.classList.toggle('is-open', open);
                toggle?.setAttribute('aria-expanded', open ? 'true' : 'false');
            };

            setOpen(desktop.matches);
            toggle?.addEventListener('click', () => setOpen(!panel?.classList.contains('is-open')));
            close?.addEventListener('click', () => setOpen(false));
            desktop.addEventListener?.('change', (event) => setOpen(event.matches));
        })();
    </script>

    <script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
    <script>
        (() => {
            const initialLocations = @json($initialLocations);
            const endpoint = @json(route('admin.dashboard.live-locations'));
            const listElement = document.getElementById('salesman-list');
            const searchInput = document.getElementById('map-search');
            const statusFilter = document.getElementById('map-status-filter');
            const onDutyFilter = document.getElementById('map-on-duty');
            const statusText = document.getElementById('map-status-text');
            const routeToggle = document.getElementById('route-toggle');
            const fitAllButton = document.getElementById('fit-all');

            const map = L.map('live-map', {
                zoomControl: false,
                attributionControl: true,
            }).setView([34.5553, 69.2075], 11);

            L.control.zoom({position: 'bottomright'}).addTo(map);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors',
            }).addTo(map);

            const palette = ['#6366f1', '#0ea5e9', '#14b8a6', '#f59e0b', '#ec4899', '#8b5cf6', '#22c55e', '#f97316'];
            const markers = new Map();
            const routes = new Map();
            const routeLayer = L.layerGroup().addTo(map);
            const filters = {search: '', status: 'all', onDuty: false};

            let locations = initialLocations;
            let selectedId = null;
            let routesVisible = true;
            let routeColorIndex = 0;
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

            const setTrack = (item) => {
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

            const popupFor = (item) => {
                const box = document.createElement('div');
                box.style.minWidth = '220px';

                const title = document.createElement('div');
                title.style.fontWeight = '700';
                title.style.marginBottom = '6px';
                title.textContent = item.salesman_name;
                box.appendChild(title);

                const line = (label, value) => {
                    const row = document.createElement('div');
                    const strong = document.createElement('strong');
                    strong.textContent = label + ': ';
                    row.appendChild(strong);
                    row.appendChild(document.createTextNode(value ?? '—'));
                    return row;
                };

                box.appendChild(line('Status', item.status));
                box.appendChild(line('On duty', item.on_duty ? 'Yes' : 'No'));

                const entry = routes.get(item.salesman_id);
                if (entry && entry.distanceKm != null) {
                    box.appendChild(line('Route distance', Number(entry.distanceKm).toFixed(1) + ' km'));
                }

                if (item.location) {
                    box.appendChild(line('Recorded', item.location.recorded_at));
                    box.appendChild(line('Battery', item.location.battery_level == null ? '—' : item.location.battery_level + '%'));
                    box.appendChild(line('Network', item.location.network_status));
                }

                return box;
            };

            const syncMarker = (item) => {
                const location = item.location;

                if (!location) {
                    const existing = markers.get(item.salesman_id);
                    if (existing) {
                        map.removeLayer(existing);
                        markers.delete(item.salesman_id);
                    }
                    return;
                }

                const point = [location.latitude, location.longitude];
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
                marker.off('click');
                marker.on('click', () => select(item.salesman_id, false));
            };

            const removeSalesman = (salesmanId) => {
                const marker = markers.get(salesmanId);
                if (marker) {
                    map.removeLayer(marker);
                    markers.delete(salesmanId);
                }

                const entry = routes.get(salesmanId);
                if (entry) {
                    if (entry.line) {
                        routeLayer.removeLayer(entry.line);
                    }
                    if (entry.start) {
                        routeLayer.removeLayer(entry.start);
                    }
                    routes.delete(salesmanId);
                }
            };

            const matchesFilters = (item) => {
                if (filters.status !== 'all' && item.status !== filters.status) {
                    return false;
                }

                if (filters.onDuty && !item.on_duty) {
                    return false;
                }

                if (filters.search !== '') {
                    const haystack = (item.salesman_name + ' ' + item.employee_code).toLowerCase();
                    if (!haystack.includes(filters.search)) {
                        return false;
                    }
                }

                return true;
            };

            const statusOrder = (item) => ({online: 0, idle: 1, offline: 2}[item.status] ?? 3);

            const renderList = () => {
                listElement.replaceChildren();

                const visible = locations
                    .filter(matchesFilters)
                    .sort((a, b) => statusOrder(a) - statusOrder(b) || a.salesman_name.localeCompare(b.salesman_name));

                if (visible.length === 0) {
                    const empty = document.createElement('div');
                    empty.className = 'px-4 py-10 text-center text-sm text-slate-400';
                    empty.textContent = 'No salesmen match the current filters.';
                    listElement.appendChild(empty);
                    return;
                }

                for (const item of visible) {
                    const entry = routes.get(item.salesman_id);
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'flex w-full flex-col gap-2 px-4 py-3 text-left transition hover:bg-white/5 '
                        + (selectedId === item.salesman_id ? 'bg-indigo-500/10 ring-1 ring-inset ring-indigo-400/30' : '');

                    const top = document.createElement('div');
                    top.className = 'flex w-full items-center justify-between gap-3';

                    const identity = document.createElement('div');
                    identity.className = 'min-w-0';

                    const name = document.createElement('div');
                    name.className = 'truncate text-sm font-medium text-slate-100';
                    name.textContent = item.salesman_name;
                    identity.appendChild(name);

                    const code = document.createElement('div');
                    code.className = 'text-xs text-slate-500';
                    code.textContent = item.employee_code + (item.on_duty ? ' · on duty' : '');
                    identity.appendChild(code);

                    top.appendChild(identity);

                    const badge = document.createElement('span');
                    const badgeClass = item.status === 'online'
                        ? 'bg-emerald-500/15 text-emerald-300'
                        : (item.status === 'idle' ? 'bg-amber-500/15 text-amber-300' : 'bg-slate-700 text-slate-300');
                    badge.className = 'shrink-0 rounded-full px-2.5 py-1 text-xs ' + badgeClass;
                    badge.textContent = item.status.charAt(0).toUpperCase() + item.status.slice(1);
                    top.appendChild(badge);

                    button.appendChild(top);

                    const meta = document.createElement('div');
                    meta.className = 'flex w-full flex-wrap items-center justify-between gap-2 text-xs text-slate-400';

                    const updated = document.createElement('span');
                    updated.textContent = item.location?.recorded_at
                        ? 'Updated ' + new Date(item.location.recorded_at).toLocaleTimeString()
                        : 'No position reported';
                    meta.appendChild(updated);

                    const distance = document.createElement('span');
                    distance.textContent = entry && entry.distanceKm != null
                        ? Number(entry.distanceKm).toFixed(1) + ' km today'
                        : 'No route';
                    meta.appendChild(distance);

                    button.appendChild(meta);

                    button.addEventListener('click', () => select(item.salesman_id, true));
                    listElement.appendChild(button);
                }
            };

            const select = (salesmanId, fitRoute) => {
                selectedId = salesmanId;
                renderList();

                const item = locations.find((candidate) => candidate.salesman_id === salesmanId);
                const marker = markers.get(salesmanId);

                if (item && marker) {
                    marker.openPopup();
                }

                const entry = routes.get(salesmanId);

                if (fitRoute && entry && entry.line) {
                    map.fitBounds(entry.line.getBounds(), {padding: [40, 40], maxZoom: 16});
                } else if (item && item.location) {
                    map.panTo([item.location.latitude, item.location.longitude]);
                }
            };

            const refreshCounts = () => {
                const counts = {online: 0, idle: 0, offline: 0};
                for (const item of locations) {
                    counts[item.status] = (counts[item.status] ?? 0) + 1;
                }

                document.getElementById('count-online').textContent = counts.online;
                document.getElementById('count-idle').textContent = counts.idle;
                document.getElementById('count-offline').textContent = counts.offline;
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

            const fitAll = () => {
                const points = [];
                for (const item of locations) {
                    if (item.location) {
                        points.push([item.location.latitude, item.location.longitude]);
                    }
                }

                for (const entry of routes.values()) {
                    if (entry.line) {
                        for (const latlng of entry.line.getLatLngs()) {
                            points.push(latlng);
                        }
                    }
                }

                if (points.length > 0) {
                    map.fitBounds(points, {padding: [30, 30], maxZoom: 15});
                }
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
                    const previousTracks = new Map(locations.map((item) => [item.salesman_id, item.track]));
                    locations = payload.data.map((item) => {
                        const entry = routes.get(item.salesman_id);
                        return {
                            ...item,
                            track: item.track ?? previousTracks.get(item.salesman_id) ?? null,
                        };
                    });

                    const seen = new Set();

                    for (const item of locations) {
                        seen.add(item.salesman_id);
                        setTrack(item);
                        appendPoint(item);
                        syncMarker(item);
                    }

                    for (const salesmanId of [...markers.keys()]) {
                        if (!seen.has(salesmanId)) {
                            removeSalesman(salesmanId);
                        }
                    }

                    if (!hasFitted && locations.some((item) => item.location)) {
                        fitAll();
                        hasFitted = true;
                    }

                    renderList();
                    refreshCounts();

                    const online = locations.filter((item) => item.status === 'online').length;
                    const idle = locations.filter((item) => item.status === 'idle').length;
                    const offline = locations.filter((item) => item.status === 'offline').length;
                    statusText.textContent = 'Online ' + online + ' · Idle ' + idle + ' · Offline ' + offline
                        + ' · refreshed ' + new Date(payload.generated_at).toLocaleTimeString();
                } catch (error) {
                    statusText.textContent = 'Live map refresh failed. Retrying automatically.';
                }
            };

            searchInput.addEventListener('input', () => {
                filters.search = searchInput.value.trim().toLowerCase();
                renderList();
            });

            statusFilter.addEventListener('change', () => {
                filters.status = statusFilter.value;
                renderList();
            });

            onDutyFilter.addEventListener('change', () => {
                filters.onDuty = onDutyFilter.checked;
                renderList();
            });

            routeToggle.addEventListener('click', () => {
                routesVisible = !routesVisible;
                applyRouteVisibility();
            });

            fitAllButton.addEventListener('click', fitAll);

            for (const item of locations) {
                setTrack(item);
                syncMarker(item);
            }

            renderList();
            refreshCounts();
            applyRouteVisibility();
            if (locations.some((item) => item.location)) {
                fitAll();
                hasFitted = true;
            }

            refresh();
            window.setInterval(refresh, 30000);
        })();
    </script>
</x-layouts.app>
