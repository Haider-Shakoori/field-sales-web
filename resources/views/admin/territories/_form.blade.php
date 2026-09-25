@php
    $polygonValue = old(
        'polygon',
        isset($territory) && $territory->polygon ? json_encode($territory->polygon) : ''
    );
    $initialGeometry = $polygonValue ? json_decode($polygonValue, true) : null;
@endphp

<link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">

<div class="grid gap-5 md:grid-cols-2">
    <label class="block">
        <span class="text-sm text-slate-300">Code</span>
        <input name="code" value="{{ old('code', $territory->code ?? '') }}" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3" required>
    </label>

    <label class="block">
        <span class="text-sm text-slate-300">Name</span>
        <input name="name" value="{{ old('name', $territory->name ?? '') }}" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3" required>
    </label>

    <label class="block md:col-span-2">
        <span class="text-sm text-slate-300">Branch</span>
        <select name="branch_id" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3">
            <option value="">Company-wide</option>
            @foreach($branches as $branch)
                <option value="{{ $branch->id }}" @selected((string) old('branch_id', $territory->branch_id ?? '') === (string) $branch->id)>{{ $branch->name }}</option>
            @endforeach
        </select>
    </label>

    <label class="block md:col-span-2">
        <span class="text-sm text-slate-300">Description</span>
        <textarea name="description" rows="3" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3">{{ old('description', $territory->description ?? '') }}</textarea>
    </label>

    <div class="md:col-span-2">
        <div class="mb-3 flex flex-wrap items-start justify-between gap-3">
            <div>
                <span class="text-sm font-medium text-slate-200">Territory geofence</span>
                <p class="mt-1 max-w-2xl text-xs leading-5 text-slate-500">
                    Click around the map to draw the territory boundary. Add at least 3 points. You no longer need to write GeoJSON manually.
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button id="territory-start-boundary" type="button" class="rounded-lg border border-indigo-400/20 bg-indigo-500/10 px-3 py-2 text-xs font-semibold text-indigo-200 hover:bg-indigo-500/20">Start new boundary</button>
                <button id="territory-undo-point" type="button" class="rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold text-slate-300 hover:bg-white/10">Undo point</button>
                <button id="territory-clear-boundary" type="button" class="rounded-lg border border-rose-400/20 bg-rose-500/10 px-3 py-2 text-xs font-semibold text-rose-200 hover:bg-rose-500/20">Clear</button>
            </div>
        </div>

        <input id="territory-polygon" type="hidden" name="polygon" value="{{ $polygonValue }}">
        <div id="territory-boundary-map" class="h-[470px] overflow-hidden rounded-2xl border border-white/10 bg-slate-950"></div>

        <div class="mt-2 flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500">
            <span id="territory-boundary-status">
                @if($initialGeometry)
                    Existing boundary loaded. Click "Start new boundary" to replace it.
                @else
                    Click the map to add the first boundary point.
                @endif
            </span>
            <span id="territory-point-count">0 points</span>
        </div>

        @error('polygon')<p class="mt-2 text-sm text-rose-300">{{ $message }}</p>@enderror
    </div>

    <label class="flex items-center gap-3 md:col-span-2">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" class="h-5 w-5 rounded" @checked((bool) old('is_active', $territory->is_active ?? true))>
        <span>Active territory</span>
    </label>
</div>

<script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
<script>
(() => {
    const mapElement = document.getElementById('territory-boundary-map');
    const polygonInput = document.getElementById('territory-polygon');
    const status = document.getElementById('territory-boundary-status');
    const pointCount = document.getElementById('territory-point-count');
    const startButton = document.getElementById('territory-start-boundary');
    const undoButton = document.getElementById('territory-undo-point');
    const clearButton = document.getElementById('territory-clear-boundary');

    if (!mapElement || !polygonInput || typeof L === 'undefined') return;

    const rawInitialGeometry = @json($initialGeometry);
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
    const initialGeometry = normalizeGeometry(rawInitialGeometry);
    const defaultCenter = [34.5553, 69.2075];
    const map = L.map(mapElement).setView(defaultCenter, 11);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors',
    }).addTo(map);

    const geometryStyle = {
        color: '#818cf8',
        weight: 3,
        opacity: 0.95,
        fillColor: '#6366f1',
        fillOpacity: 0.16,
    };

    let existingLayer = null;
    let drawingLayer = null;
    let vertexMarkers = [];
    let points = [];
    let drawing = !initialGeometry;

    const removeExistingLayer = () => {
        if (existingLayer) {
            map.removeLayer(existingLayer);
            existingLayer = null;
        }
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

        return {
            type: 'Polygon',
            coordinates: [ring],
        };
    };

    const updateInput = () => {
        const geometry = geometryFromPoints();
        polygonInput.value = geometry ? JSON.stringify(geometry) : '';
        pointCount.textContent = points.length + (points.length === 1 ? ' point' : ' points');

        if (!drawing) return;

        if (points.length === 0) {
            status.textContent = 'Click the map to add the first boundary point.';
        } else if (points.length < 3) {
            status.textContent = 'Add ' + (3 - points.length) + ' more point' + (points.length === 2 ? '' : 's') + ' to create a territory.';
        } else {
            status.textContent = 'Boundary ready. Continue clicking to refine it or drag any point.';
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
            const marker = L.marker([point.lat, point.lng], {draggable: true}).addTo(map);

            marker.bindTooltip('Point ' + (index + 1), {
                permanent: false,
                direction: 'top',
            });

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
        redraw();
    };

    if (initialGeometry) {
        try {
            existingLayer = L.geoJSON(initialGeometry, {style: geometryStyle}).addTo(map);
            const bounds = existingLayer.getBounds();

            if (bounds.isValid()) {
                map.fitBounds(bounds.pad(0.12), {maxZoom: 15});
            }

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
                status.textContent = 'Boundary loaded. Drag points or click the map to refine it.';
            } else {
                pointCount.textContent = initialGeometry.type === 'MultiPolygon' ? 'MultiPolygon' : 'Boundary loaded';
                status.textContent = initialGeometry.type === 'MultiPolygon'
                    ? 'MultiPolygon loaded. Click "Start new boundary" to replace it with a newly drawn territory.'
                    : 'Existing boundary loaded.';
            }
        } catch (_) {
            drawing = true;
            polygonInput.value = '';
            status.textContent = 'The saved geometry could not be displayed. Draw a new boundary.';
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
        if (!drawing) {
            status.textContent = 'Click "Start new boundary" before editing the saved boundary.';
            return;
        }

        points.pop();
        redraw();
    });

    clearButton?.addEventListener('click', () => {
        removeExistingLayer();
        removeDrawingLayer();
        points = [];
        drawing = true;
        polygonInput.value = '';
        redraw();
    });

    setTimeout(() => map.invalidateSize(), 0);
})();
</script>
