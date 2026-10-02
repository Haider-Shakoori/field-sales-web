@php
    $polygonValue = old(
        'geofence_polygon',
        isset($branch) && $branch->geofence_polygon ? json_encode($branch->geofence_polygon) : ''
    );
    $initialGeometry = $polygonValue ? json_decode($polygonValue, true) : null;
@endphp

<link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">

<div class="grid gap-5 md:grid-cols-2">
    <label class="block">
        <span class="text-sm text-slate-300">{{ __('Branch name') }}</span>
        <input name="name" value="{{ old('name', $branch->name ?? '') }}" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3" required>
    </label>

    <label class="block">
        <span class="text-sm text-slate-300">{{ __('Code') }}</span>
        <input name="code" value="{{ old('code', $branch->code ?? '') }}" placeholder="KBL" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3 uppercase" required>
    </label>

    <div class="md:col-span-2">
        <div class="mb-3 flex flex-wrap items-start justify-between gap-3">
            <div>
                <span class="text-sm font-medium text-slate-200">{{ __('Branch operating geofence') }}</span>
                <p class="mt-1 max-w-2xl text-xs leading-5 text-slate-500">
                    {{ __('Draw the branch operating area directly on the map. Click at least 3 points around the boundary; FieldPulse stores the geometry automatically.') }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button id="branch-start-boundary" type="button" class="rounded-lg border border-indigo-400/20 bg-indigo-500/10 px-3 py-2 text-xs font-semibold text-indigo-200 hover:bg-indigo-500/20">{{ __('Start new boundary') }}</button>
                <button id="branch-undo-point" type="button" class="rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold text-slate-300 hover:bg-white/10">{{ __('Undo point') }}</button>
                <button id="branch-fit-boundary" type="button" class="rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold text-slate-300 hover:bg-white/10">{{ __('Fit boundary') }}</button>
                <button id="branch-clear-boundary" type="button" class="rounded-lg border border-rose-400/20 bg-rose-500/10 px-3 py-2 text-xs font-semibold text-rose-200 hover:bg-rose-500/20">{{ __('Clear') }}</button>
            </div>
        </div>

        <input id="branch-geofence-polygon" type="hidden" name="geofence_polygon" value="{{ $polygonValue }}">
        <div id="branch-geofence-map" class="overflow-hidden rounded-2xl border border-white/10 bg-slate-950" style="height:470px;min-height:320px;"></div>

        <div class="mt-2 flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500">
            <span id="branch-boundary-status">
                @if($initialGeometry)
                    {{ __('Existing branch boundary loaded. Drag points when editable, or start a new boundary to replace it.') }}
                @else
                    {{ __('Click the map to add the first boundary point.') }}
                @endif
            </span>
            <span id="branch-point-count">0 {{ __('points') }}</span>
        </div>

        @error('geofence_polygon')<p class="mt-2 text-sm text-rose-300">{{ $message }}</p>@enderror
    </div>

    <label class="flex items-center gap-3 md:col-span-2">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" class="h-5 w-5 rounded" @checked((bool) old('is_active', $branch->is_active ?? true))>
        <span>
            <span class="block font-medium">{{ __('Active branch') }}</span>
            <span class="block text-sm text-slate-400">{{ __('Inactive branches stay in history but cannot be selected for new users.') }}</span>
        </span>
    </label>
</div>

<script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
<script>
(() => {
    const mapElement = document.getElementById('branch-geofence-map');
    const polygonInput = document.getElementById('branch-geofence-polygon');
    const status = document.getElementById('branch-boundary-status');
    const pointCount = document.getElementById('branch-point-count');
    const startButton = document.getElementById('branch-start-boundary');
    const undoButton = document.getElementById('branch-undo-point');
    const fitButton = document.getElementById('branch-fit-boundary');
    const clearButton = document.getElementById('branch-clear-boundary');

    if (!mapElement || !polygonInput || typeof L === 'undefined') return;

    const rawInitialGeometry = @json($initialGeometry);
    const defaultCenter = [34.5553, 69.2075];

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

                if (first[0] !== last[0] || first[1] !== last[1]) ring.push([...first]);

                return {type: 'Polygon', coordinates: [ring]};
            }
        }

        return null;
    };

    const initialGeometry = normalizeGeometry(rawInitialGeometry);
    const map = L.map(mapElement).setView(defaultCenter, 11);

    L.tileLayer('{{ config('maps.tile_url') }}', {
        maxZoom: 19,
        attribution: @json(config('maps.attribution')),
    }).addTo(map);

    const geometryStyle = {
        color: '#818cf8',
        weight: 3,
        opacity: 0.95,
        fillColor: '#6366f1',
        fillOpacity: 0.16,
    };

    const vertexIcon = L.divIcon({
        className: '',
        html: '<div style="width:18px;height:18px;border-radius:9999px;background:#818cf8;border:3px solid white;box-shadow:0 3px 10px rgba(15,23,42,.4)"></div>',
        iconSize: [18, 18],
        iconAnchor: [9, 9],
    });

    let existingLayer = null;
    let drawingLayer = null;
    let vertexMarkers = [];
    let points = [];
    let drawing = !initialGeometry;

    const removeExistingLayer = () => {
        if (!existingLayer) return;
        map.removeLayer(existingLayer);
        existingLayer = null;
    };

    const removeDrawingLayer = () => {
        if (drawingLayer) {
            map.removeLayer(drawingLayer);
            drawingLayer = null;
        }

        vertexMarkers.forEach((marker) => map.removeLayer(marker));
        vertexMarkers = [];
    };

    const geometryFromPoints = () => {
        if (points.length < 3) return null;

        const ring = points.map((point) => [point.lng, point.lat]);
        ring.push([points[0].lng, points[0].lat]);

        return {type: 'Polygon', coordinates: [ring]};
    };

    const updateInput = () => {
        if (drawing) {
            const geometry = geometryFromPoints();
            polygonInput.value = geometry ? JSON.stringify(geometry) : '';
        }

        pointCount.textContent = points.length
            ? points.length + (points.length === 1 ? ' point' : ' points')
            : (initialGeometry?.type === 'MultiPolygon' && !drawing ? 'MultiPolygon' : '0 points');

        if (!drawing) return;

        if (points.length === 0) {
            status.textContent = 'Click the map to add the first boundary point.';
        } else if (points.length < 3) {
            const remaining = 3 - points.length;
            status.textContent = 'Add ' + remaining + ' more point' + (remaining === 1 ? '' : 's') + ' to complete the boundary.';
        } else {
            status.textContent = 'Boundary ready. Click to add more points or drag a point to refine it.';
        }
    };

    const redraw = () => {
        removeDrawingLayer();

        if (!points.length) {
            updateInput();
            return;
        }

        const latlngs = points.map((point) => [point.lat, point.lng]);
        drawingLayer = points.length >= 3
            ? L.polygon(latlngs, geometryStyle).addTo(map)
            : L.polyline(latlngs, {color: '#818cf8', weight: 3}).addTo(map);

        vertexMarkers = points.map((point, index) => {
            const marker = L.marker([point.lat, point.lng], {
                draggable: true,
                icon: vertexIcon,
            }).addTo(map);

            marker.bindTooltip('Point ' + (index + 1), {direction: 'top'});

            marker.on('drag', () => {
                const latlng = marker.getLatLng();
                points[index] = {
                    lat: Number(latlng.lat.toFixed(7)),
                    lng: Number(latlng.lng.toFixed(7)),
                };

                if (drawingLayer) {
                    const updated = points.map((item) => [item.lat, item.lng]);
                    drawingLayer.setLatLngs(points.length >= 3 ? [updated] : updated);
                }

                updateInput();
            });

            return marker;
        });

        updateInput();
    };

    const startDrawing = () => {
        removeExistingLayer();
        removeDrawingLayer();
        points = [];
        drawing = true;
        polygonInput.value = '';
        status.textContent = 'Click the map to add the first boundary point.';
        redraw();
    };

    const fitBoundary = () => {
        const layer = drawingLayer || existingLayer;
        if (!layer || typeof layer.getBounds !== 'function') return;

        const bounds = layer.getBounds();
        if (bounds.isValid()) map.fitBounds(bounds.pad(0.12), {maxZoom: 16});
    };

    if (initialGeometry) {
        try {
            existingLayer = L.geoJSON(initialGeometry, {style: geometryStyle}).addTo(map);
            fitBoundary();

            const coordinates = initialGeometry.type === 'Polygon'
                ? initialGeometry.coordinates?.[0]
                : null;

            if (Array.isArray(coordinates) && coordinates.length >= 4) {
                points = coordinates
                    .slice(0, -1)
                    .map((coordinate) => ({
                        lng: Number(coordinate[0]),
                        lat: Number(coordinate[1]),
                    }))
                    .filter((point) => Number.isFinite(point.lat) && Number.isFinite(point.lng));

                drawing = true;
                removeExistingLayer();
                redraw();
                fitBoundary();
                status.textContent = 'Boundary loaded. Drag points or click the map to refine it.';
            } else {
                drawing = false;
                status.textContent = 'Existing multi-area boundary loaded. Start a new boundary to replace it.';
                updateInput();
            }
        } catch (_) {
            drawing = true;
            polygonInput.value = '';
            status.textContent = 'The saved boundary could not be displayed. Draw a new one.';
        }
    }

    map.on('click', (event) => {
        if (!drawing) return;

        points.push({
            lat: Number(event.latlng.lat.toFixed(7)),
            lng: Number(event.latlng.lng.toFixed(7)),
        });
        redraw();
    });

    startButton?.addEventListener('click', startDrawing);

    undoButton?.addEventListener('click', () => {
        if (!drawing || !points.length) return;
        points.pop();
        redraw();
    });

    fitButton?.addEventListener('click', fitBoundary);

    clearButton?.addEventListener('click', () => {
        removeExistingLayer();
        removeDrawingLayer();
        points = [];
        drawing = true;
        polygonInput.value = '';
        status.textContent = 'Boundary cleared. Click the map to draw a new one.';
        updateInput();
    });

    setTimeout(() => map.invalidateSize(), 100);
})();
</script>
