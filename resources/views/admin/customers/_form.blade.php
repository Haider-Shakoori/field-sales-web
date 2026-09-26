@php
    $selectedLatitude = old('latitude', $customer->latitude ?? '');
    $selectedLongitude = old('longitude', $customer->longitude ?? '');
    $selectedTerritoryId = old('territory_id', $customer->territory_id ?? '');
    $territoryMapData = $territories->map(fn ($territory) => [
        'id' => (string) $territory->id,
        'code' => $territory->code,
        'name' => $territory->name,
        'polygon' => $territory->polygon,
    ])->values();
@endphp

<link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">

<div class="grid gap-5 md:grid-cols-2">
    <label class="block">
        <span class="text-sm text-slate-300">{{ __('Code') }}</span>
        <input name="code" value="{{ old('code', $customer->code ?? '') }}" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3" required>
    </label>

    <label class="block">
        <span class="text-sm text-slate-300">{{ __('Name') }}</span>
        <input name="name" value="{{ old('name', $customer->name ?? '') }}" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3" required>
    </label>

    <label class="block">
        <span class="text-sm text-slate-300">{{ __('Branch') }}</span>
        <select name="branch_id" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3">
            <option value="">{{ __('Unassigned') }}</option>
            @foreach($branches as $branch)
                <option value="{{ $branch->id }}" @selected((string) old('branch_id', $customer->branch_id ?? '') === (string) $branch->id)>{{ $branch->name }}</option>
            @endforeach
        </select>
    </label>

    <label class="block">
        <span class="text-sm text-slate-300">{{ __('Territory') }}</span>
        <select id="customer-territory-select" name="territory_id" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3">
            <option value="">{{ __('Auto-detect from map') }}</option>
            @foreach($territories as $territory)
                <option value="{{ $territory->id }}" @selected((string) $selectedTerritoryId === (string) $territory->id)>{{ $territory->code }} — {{ $territory->name }}</option>
            @endforeach
        </select>
    </label>

    <label class="block md:col-span-2">
        <span class="text-sm text-slate-300">{{ __('Price list') }}</span>
        <select name="price_list_id" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3">
            <option value="">{{ __('Use product base prices') }}</option>
            @foreach($priceLists as $priceList)
                <option value="{{ $priceList->id }}" @selected((string) old('price_list_id', $customer->price_list_id ?? '') === (string) $priceList->id)>{{ $priceList->code }} — {{ $priceList->name }} ({{ $priceList->currency }})</option>
            @endforeach
        </select>
    </label>

    <div class="md:col-span-2 border-t border-white/10 pt-5">
        <h3 class="font-semibold">{{ __('Credit settings') }}</h3>
        <p class="mt-1 text-xs text-slate-400">{{ __('Leave the credit limit blank for no enforced limit. A zero limit requires manager override for every credit approval.') }}</p>
    </div>

    <label class="block">
        <span class="text-sm text-slate-300">{{ __('Credit limit') }}</span>
        <input type="number" min="0" step="0.01" name="credit_limit" value="{{ old('credit_limit', $customer->credit_limit ?? '') }}" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3">
    </label>

    <label class="block">
        <span class="text-sm text-slate-300">{{ __('Credit currency') }}</span>
        <select name="credit_currency" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3">
            @foreach(['AFN','USD','PKR'] as $currency)
                <option value="{{ $currency }}" @selected(old('credit_currency', $customer->credit_currency ?? 'AFN') === $currency)>{{ $currency }}</option>
            @endforeach
        </select>
    </label>

    <label class="block">
        <span class="text-sm text-slate-300">{{ __('Credit terms (days)') }}</span>
        <input type="number" min="0" max="365" name="credit_terms_days" value="{{ old('credit_terms_days', $customer->credit_terms_days ?? 30) }}" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3" required>
    </label>

    <label class="block">
        <span class="text-sm text-slate-300">{{ __('Contact person') }}</span>
        <input name="contact_person" value="{{ old('contact_person', $customer->contact_person ?? '') }}" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3">
    </label>

    <label class="block">
        <span class="text-sm text-slate-300">{{ __('Phone') }}</span>
        <input name="phone" value="{{ old('phone', $customer->phone ?? '') }}" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3">
    </label>

    <label class="block">
        <span class="text-sm text-slate-300">{{ __('Alternate phone') }}</span>
        <input name="alternate_phone" value="{{ old('alternate_phone', $customer->alternate_phone ?? '') }}" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3">
    </label>

    <label class="block">
        <span class="text-sm text-slate-300">{{ __('Email') }}</span>
        <input type="email" name="email" value="{{ old('email', $customer->email ?? '') }}" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3">
    </label>

    <label class="block md:col-span-2">
        <span class="text-sm text-slate-300">{{ __('Address') }}</span>
        <textarea name="address" rows="2" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3">{{ old('address', $customer->address ?? '') }}</textarea>
    </label>

    <div class="md:col-span-2 border-t border-white/10 pt-5">
        <div class="mb-3 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h3 class="font-semibold">{{ __('Shop location') }}</h3>
                <p class="mt-1 max-w-2xl text-xs leading-5 text-slate-400">
                    {{ __('Click the exact shop location on the map or drag the marker. The latitude and longitude will be stored automatically.') }}
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <button id="customer-current-location" type="button" class="rounded-lg border border-emerald-400/20 bg-emerald-500/10 px-3 py-2 text-xs font-semibold text-emerald-200 hover:bg-emerald-500/20">
                    {{ __('Use current location') }}
                </button>
                <button id="customer-clear-location" type="button" class="rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold text-slate-300 hover:bg-white/10">
                    {{ __('Clear location') }}
                </button>
            </div>
        </div>

        <input id="customer-latitude" type="hidden" name="latitude" value="{{ $selectedLatitude }}">
        <input id="customer-longitude" type="hidden" name="longitude" value="{{ $selectedLongitude }}">

        <div id="customer-location-map" class="overflow-hidden rounded-2xl border border-white/10 bg-slate-950" style="height:460px;min-height:320px;"></div>

        <div class="mt-3 grid gap-3 sm:grid-cols-2">
            <div class="rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ __('Latitude') }}</p>
                <p id="customer-latitude-display" class="mt-1 font-mono text-sm text-slate-200">{{ $selectedLatitude !== '' ? $selectedLatitude : '—' }}</p>
            </div>
            <div class="rounded-xl border border-white/10 bg-slate-950/70 px-4 py-3">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ __('Longitude') }}</p>
                <p id="customer-longitude-display" class="mt-1 font-mono text-sm text-slate-200">{{ $selectedLongitude !== '' ? $selectedLongitude : '—' }}</p>
            </div>
        </div>

        <div class="mt-2 flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500">
            <span id="customer-location-status">
                @if($selectedLatitude !== '' && $selectedLongitude !== '')
                    {{ __('Saved shop location loaded. Drag the marker to adjust it.') }}
                @else
                    {{ __('No shop location selected yet.') }}
                @endif
            </span>
            <span>{{ __('Selecting a territory will highlight its boundary on the map.') }}</span>
        </div>

        @error('latitude')<p class="mt-2 text-sm text-rose-300">{{ $message }}</p>@enderror
        @error('longitude')<p class="mt-2 text-sm text-rose-300">{{ $message }}</p>@enderror
    </div>

    <label class="block">
        <span class="text-sm text-slate-300">{{ __('Geofence radius (m)') }}</span>
        <input id="customer-geofence-radius" type="number" min="25" max="1000" name="geofence_radius_meters" value="{{ old('geofence_radius_meters', $customer->geofence_radius_meters ?? 100) }}" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3" required>
        <span class="mt-1 block text-xs text-slate-500">{{ __('The map previews this radius around the selected shop location.') }}</span>
    </label>

    <label class="flex items-center gap-3">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $customer->is_active ?? true))>
        <span>{{ __('Active customer') }}</span>
    </label>
</div>

<script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
<script>
(() => {
    const mapElement = document.getElementById('customer-location-map');
    const latitudeInput = document.getElementById('customer-latitude');
    const longitudeInput = document.getElementById('customer-longitude');
    const latitudeDisplay = document.getElementById('customer-latitude-display');
    const longitudeDisplay = document.getElementById('customer-longitude-display');
    const status = document.getElementById('customer-location-status');
    const clearButton = document.getElementById('customer-clear-location');
    const currentLocationButton = document.getElementById('customer-current-location');
    const territorySelect = document.getElementById('customer-territory-select');
    const radiusInput = document.getElementById('customer-geofence-radius');
    const territories = @json($territoryMapData);

    if (!mapElement || !latitudeInput || !longitudeInput || typeof L === 'undefined') return;

    const defaultCenter = [34.5553, 69.2075];
    const initialLatitude = Number.parseFloat(latitudeInput.value);
    const initialLongitude = Number.parseFloat(longitudeInput.value);
    const hasInitialLocation = Number.isFinite(initialLatitude) && Number.isFinite(initialLongitude);

    const map = L.map(mapElement, {
        zoomControl: true,
    }).setView(
        hasInitialLocation ? [initialLatitude, initialLongitude] : defaultCenter,
        hasInitialLocation ? 17 : 11,
    );

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors',
    }).addTo(map);

    const locationIcon = L.divIcon({
        className: '',
        html: '<div style="width:24px;height:24px;border-radius:9999px;background:#6366f1;border:4px solid white;box-shadow:0 5px 16px rgba(15,23,42,.5)"></div>',
        iconSize: [24, 24],
        iconAnchor: [12, 12],
    });

    let marker = null;
    let geofenceCircle = null;
    let territoryLayer = null;
    let accuracyCircle = null;

    const normalizeGeometry = (geometry) => {
        if (!geometry) return null;
        if (geometry.type && geometry.coordinates) return geometry;

        if (Array.isArray(geometry) && geometry.length >= 3) {
            const ring = geometry
                .filter((point) => Array.isArray(point) && point.length >= 2)
                .map((point) => [Number(point[1]), Number(point[0])])
                .filter((point) => Number.isFinite(point[0]) && Number.isFinite(point[1]));

            if (ring.length >= 3) {
                const first = ring[0];
                const last = ring[ring.length - 1];

                if (first[0] !== last[0] || first[1] !== last[1]) {
                    ring.push([...first]);
                }

                return {type: 'Polygon', coordinates: [ring]};
            }
        }

        return null;
    };

    const pointInRing = (ring, lat, lng) => {
        if (!Array.isArray(ring) || ring.length < 3) return false;

        let inside = false;

        for (let i = 0, j = ring.length - 1; i < ring.length; j = i++) {
            const a = ring[i];
            const b = ring[j];

            if (!Array.isArray(a) || !Array.isArray(b) || a.length < 2 || b.length < 2) {
                continue;
            }

            const xi = Number(a[0]);
            const yi = Number(a[1]);
            const xj = Number(b[0]);
            const yj = Number(b[1]);

            if (![xi, yi, xj, yj].every(Number.isFinite)) continue;

            const crosses = ((yi > lat) !== (yj > lat))
                && (lng < ((xj - xi) * (lat - yi) / ((yj - yi) || 1e-12)) + xi);

            if (crosses) inside = !inside;
        }

        return inside;
    };

    const geometryContains = (geometry, lat, lng) => {
        const normalized = normalizeGeometry(geometry);
        if (!normalized) return false;

        const polygonContains = (rings) => {
            if (!Array.isArray(rings) || !pointInRing(rings[0], lat, lng)) return false;

            return !rings.slice(1).some((hole) => pointInRing(hole, lat, lng));
        };

        if (normalized.type === 'Polygon') {
            return polygonContains(normalized.coordinates);
        }

        if (normalized.type === 'MultiPolygon') {
            return normalized.coordinates?.some((polygon) => polygonContains(polygon)) ?? false;
        }

        return false;
    };

    const detectTerritory = (lat, lng) =>
        territories.find((territory) => geometryContains(territory.polygon, lat, lng)) ?? null;

    const radius = () => {
        const parsed = Number.parseFloat(radiusInput?.value ?? '100');
        return Number.isFinite(parsed) && parsed > 0 ? parsed : 100;
    };

    const redrawGeofence = (latlng) => {
        if (geofenceCircle) {
            map.removeLayer(geofenceCircle);
            geofenceCircle = null;
        }

        if (!latlng) return;

        geofenceCircle = L.circle(latlng, {
            radius: radius(),
            color: '#22c55e',
            weight: 2,
            opacity: .75,
            fillColor: '#22c55e',
            fillOpacity: .08,
            interactive: false,
        }).addTo(map);
    };

    const updateLocation = (latlng, message = null) => {
        const lat = Number(latlng.lat.toFixed(7));
        const lng = Number(latlng.lng.toFixed(7));

        latitudeInput.value = lat;
        longitudeInput.value = lng;
        latitudeDisplay.textContent = lat.toFixed(7);
        longitudeDisplay.textContent = lng.toFixed(7);
        status.textContent = message ?? 'Shop location selected. Drag the marker for fine adjustment.';

        if (!marker) {
            marker = L.marker([lat, lng], {
                draggable: true,
                icon: locationIcon,
                title: 'Shop location',
            }).addTo(map);

            marker.on('drag', () => {
                redrawGeofence(marker.getLatLng());
            });

            marker.on('dragend', () => {
                updateLocation(marker.getLatLng(), 'Shop location adjusted.');
            });
        } else {
            marker.setLatLng([lat, lng]);
        }

        redrawGeofence([lat, lng]);

        const detected = detectTerritory(lat, lng);

        if (territorySelect) {
            territorySelect.value = detected ? String(detected.id) : '';
            renderTerritory(territorySelect.value, false);
        }

        if (detected) {
            status.textContent = (message ?? 'Shop location selected.')
                + ' Territory: ' + detected.code + ' — ' + detected.name + '.';
        } else if (!message) {
            status.textContent = 'Shop location selected. No mapped territory contains this point yet.';
        }
    };

    const removeLocation = () => {
        latitudeInput.value = '';
        longitudeInput.value = '';
        latitudeDisplay.textContent = '—';
        longitudeDisplay.textContent = '—';
        status.textContent = 'No shop location selected yet.';

        if (marker) {
            map.removeLayer(marker);
            marker = null;
        }

        if (geofenceCircle) {
            map.removeLayer(geofenceCircle);
            geofenceCircle = null;
        }

        if (accuracyCircle) {
            map.removeLayer(accuracyCircle);
            accuracyCircle = null;
        }
    };

    const renderTerritory = (territoryId, shouldFit = true) => {
        if (territoryLayer) {
            map.removeLayer(territoryLayer);
            territoryLayer = null;
        }

        if (!territoryId) return;

        const territory = territories.find((row) => String(row.id) === String(territoryId));
        const geometry = normalizeGeometry(territory?.polygon);

        if (!geometry) {
            status.textContent = 'Selected territory has no mapped boundary. You can still choose the shop location manually.';
            return;
        }

        try {
            territoryLayer = L.geoJSON(geometry, {
                style: {
                    color: '#38bdf8',
                    weight: 3,
                    opacity: .9,
                    fillColor: '#38bdf8',
                    fillOpacity: .07,
                },
            }).addTo(map);

            if (shouldFit && !marker) {
                const bounds = territoryLayer.getBounds();

                if (bounds.isValid()) {
                    map.fitBounds(bounds.pad(.12), {maxZoom: 15});
                }
            }
        } catch (_) {
            status.textContent = 'The selected territory boundary could not be displayed.';
        }
    };

    if (hasInitialLocation) {
        updateLocation(
            {lat: initialLatitude, lng: initialLongitude},
            'Saved shop location loaded. Drag the marker to adjust it.',
        );
    }

    renderTerritory(territorySelect?.value, !hasInitialLocation);

    map.on('click', (event) => {
        updateLocation(event.latlng);
    });

    radiusInput?.addEventListener('input', () => {
        redrawGeofence(marker?.getLatLng() ?? null);
    });

    territorySelect?.addEventListener('change', () => {
        renderTerritory(territorySelect.value, true);
    });

    clearButton?.addEventListener('click', removeLocation);

    currentLocationButton?.addEventListener('click', () => {
        if (!navigator.geolocation) {
            status.textContent = 'Current location is not available in this browser.';
            return;
        }

        currentLocationButton.disabled = true;
        status.textContent = 'Finding your current location…';

        navigator.geolocation.getCurrentPosition(
            (position) => {
                const latlng = {
                    lat: position.coords.latitude,
                    lng: position.coords.longitude,
                };

                updateLocation(latlng, 'Current device location selected. Drag the marker if the shop entrance is slightly different.');
                map.setView([latlng.lat, latlng.lng], 18);

                if (accuracyCircle) {
                    map.removeLayer(accuracyCircle);
                }

                accuracyCircle = L.circle([latlng.lat, latlng.lng], {
                    radius: Math.max(position.coords.accuracy ?? 0, 1),
                    color: '#94a3b8',
                    weight: 1,
                    opacity: .5,
                    fillColor: '#94a3b8',
                    fillOpacity: .06,
                    interactive: false,
                }).addTo(map);

                currentLocationButton.disabled = false;
            },
            () => {
                status.textContent = 'Could not read the current location. Allow location permission or click the map instead.';
                currentLocationButton.disabled = false;
            },
            {
                enableHighAccuracy: true,
                timeout: 12000,
                maximumAge: 30000,
            },
        );
    });

    setTimeout(() => map.invalidateSize(), 0);
})();
</script>
