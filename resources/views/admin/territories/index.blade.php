<x-layouts.app>
    @php
        $territoryMapData = $mapTerritories->map(fn ($territory) => [
            'id' => $territory->id,
            'code' => $territory->code,
            'name' => $territory->name,
            'branch' => $territory->branch?->name ?? 'Company-wide',
            'active' => $territory->is_active,
            'polygon' => $territory->polygon,
            'url' => route('admin.territories.show', $territory),
        ])->values();
    @endphp

    <link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">

    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold">Territories</h1>
            <p class="mt-1 text-sm text-slate-400">See all mapped sales territories together and open any territory from the map.</p>
        </div>
        @if(auth()->user()->hasPermission('customers:manage'))
            <a href="{{ route('admin.territories.create') }}" class="rounded-xl bg-indigo-500 px-4 py-2.5 font-semibold">Add territory</a>
        @endif
    </div>

    <section class="mb-6 overflow-hidden rounded-2xl border border-white/10 bg-slate-900">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/10 px-4 py-3">
            <div>
                <h2 class="font-semibold">Territory map</h2>
                <p class="mt-0.5 text-xs text-slate-500">{{ $mapTerritories->count() }} mapped territories</p>
            </div>
            <button id="fit-territories" type="button" class="rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold text-slate-300 hover:bg-white/10">Fit all territories</button>
        </div>
        <div id="territories-map" class="h-[520px] bg-slate-950"></div>
    </section>

    <div class="grid gap-4 lg:grid-cols-2">
        @forelse($territories as $territory)
            <a href="{{ route('admin.territories.show', $territory) }}" class="rounded-2xl border border-white/10 bg-slate-900 p-5 hover:bg-slate-800">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="font-semibold">{{ $territory->code }} — {{ $territory->name }}</h2>
                    <span class="text-xs text-slate-400">{{ $territory->is_active ? 'Active' : 'Inactive' }}</span>
                </div>
                <p class="mt-2 text-sm text-slate-400">{{ $territory->branch?->name ?? 'Company-wide' }}</p>
                <p class="mt-3 text-sm">{{ $territory->customers_count }} customers · {{ $territory->routes_count }} routes</p>
            </a>
        @empty
            <div class="rounded-2xl border border-white/10 bg-slate-900 p-8 text-slate-400">No territories found.</div>
        @endforelse
    </div>

    <div class="mt-5">{{ $territories->links() }}</div>

    <script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
    <script>
    (() => {
        const mapElement = document.getElementById('territories-map');
        const fitButton = document.getElementById('fit-territories');
        const territories = @json($territoryMapData);

        if (!mapElement || typeof L === 'undefined') return;

        const map = L.map(mapElement).setView([34.5553, 69.2075], 11);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors',
        }).addTo(map);

        const palette = ['#6366f1', '#0ea5e9', '#14b8a6', '#f59e0b', '#ec4899', '#8b5cf6', '#22c55e', '#f97316'];
        const group = L.featureGroup().addTo(map);

        const escapeHtml = (value) => {
            const div = document.createElement('div');
            div.textContent = value ?? '';
            return div.innerHTML;
        };

        territories.forEach((territory, index) => {
            if (!territory.polygon) return;

            try {
                const color = palette[index % palette.length];
                const layer = L.geoJSON(territory.polygon, {
                    style: {
                        color,
                        weight: territory.active ? 3 : 2,
                        opacity: territory.active ? 0.95 : 0.45,
                        fillColor: color,
                        fillOpacity: territory.active ? 0.12 : 0.05,
                    },
                });

                layer.bindPopup(
                    '<div style="min-width:180px">' +
                    '<strong>' + escapeHtml(territory.code + ' — ' + territory.name) + '</strong>' +
                    '<div style="margin-top:4px;color:#64748b;font-size:12px">' + escapeHtml(territory.branch) + '</div>' +
                    '<a href="' + escapeHtml(territory.url) + '" style="display:inline-block;margin-top:8px;font-size:12px;font-weight:600">Open territory</a>' +
                    '</div>'
                );

                layer.addTo(group);
            } catch (_) {}
        });

        const fitAll = () => {
            const bounds = group.getBounds();

            if (bounds.isValid()) {
                map.fitBounds(bounds.pad(0.06), {maxZoom: 14});
            }
        };

        fitButton?.addEventListener('click', fitAll);
        fitAll();
        setTimeout(() => map.invalidateSize(), 0);
    })();
    </script>
</x-layouts.app>
