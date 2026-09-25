<x-layouts.app>
    <link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">

    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold">{{ $territory->name }}</h1>
            <p class="mt-1 text-sm text-slate-400">{{ $territory->code }} · {{ $territory->branch?->name ?? 'Company-wide' }}</p>
        </div>
        @if(auth()->user()->hasPermission('customers:manage'))
            <a href="{{ route('admin.territories.edit', $territory) }}" class="rounded-xl bg-indigo-500 px-4 py-2.5 font-semibold">Edit</a>
        @endif
    </div>

    @if($territory->polygon)
        <section class="mb-5 overflow-hidden rounded-2xl border border-white/10 bg-slate-900">
            <div class="border-b border-white/10 px-5 py-4">
                <h2 class="font-semibold">Territory boundary</h2>
                <p class="mt-1 text-xs text-slate-500">The stored geofence for this territory.</p>
            </div>
            <div id="territory-detail-map" class="h-[430px] bg-slate-950"></div>
        </section>
    @endif

    <div class="grid gap-5 lg:grid-cols-2">
        <section class="rounded-2xl border border-white/10 bg-slate-900 p-5">
            <h2 class="font-semibold">Customers</h2>
            <div class="mt-4 max-h-[560px] space-y-2 overflow-y-auto">
                @forelse($territory->customers as $customer)
                    <a href="{{ route('admin.customers.show', $customer) }}" class="block rounded-xl bg-slate-950 p-3">{{ $customer->code }} — {{ $customer->name }}</a>
                @empty
                    <p class="text-sm text-slate-400">No customers.</p>
                @endforelse
            </div>
        </section>

        <section class="rounded-2xl border border-white/10 bg-slate-900 p-5">
            <h2 class="font-semibold">Routes</h2>
            <div class="mt-4 space-y-2">
                @forelse($territory->routes as $route)
                    <a href="{{ route('admin.routes.show', $route) }}" class="block rounded-xl bg-slate-950 p-3">{{ $route->code }} — {{ $route->name }}</a>
                @empty
                    <p class="text-sm text-slate-400">No routes.</p>
                @endforelse
            </div>
        </section>
    </div>

    @if($territory->polygon)
        <script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
        <script>
        (() => {
            const mapElement = document.getElementById('territory-detail-map');
            const geometry = @json($territory->polygon);

            if (!mapElement || !geometry || typeof L === 'undefined') return;

            const map = L.map(mapElement).setView([34.5553, 69.2075], 11);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors',
            }).addTo(map);

            try {
                const layer = L.geoJSON(geometry, {
                    style: {
                        color: '#6366f1',
                        weight: 3,
                        opacity: 0.95,
                        fillColor: '#6366f1',
                        fillOpacity: 0.14,
                    },
                }).addTo(map);

                const bounds = layer.getBounds();

                if (bounds.isValid()) {
                    map.fitBounds(bounds.pad(0.08), {maxZoom: 15});
                }
            } catch (_) {}

            setTimeout(() => map.invalidateSize(), 0);
        })();
        </script>
    @endif
</x-layouts.app>
