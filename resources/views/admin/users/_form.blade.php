@php
    $editing = isset($managedUser);
    $currentRoleId = old('role_id', $editing ? $managedUser->roles->first()?->id : null);
    $selectedLatitude = old('latitude', $managedUser->latitude ?? '');
    $selectedLongitude = old('longitude', $managedUser->longitude ?? '');
@endphp

<link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">

<div class="grid gap-5 md:grid-cols-2">
    <label class="block md:col-span-2">
        <span class="text-sm text-slate-300">Name</span>
        <input name="name" value="{{ old('name', $managedUser->name ?? '') }}" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3" required>
    </label>

    <label class="block md:col-span-2">
        <span class="text-sm text-slate-300">Email</span>
        <input type="email" name="email" value="{{ old('email', $managedUser->email ?? '') }}" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3" required>
    </label>

    <label class="block">
        <span class="text-sm text-slate-300">Role</span>
        <select name="role_id" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3" required>
            <option value="">Select a role</option>
            @foreach($roles as $role)
                <option value="{{ $role->id }}" @selected((string) $currentRoleId === (string) $role->id)>{{ $role->name }}</option>
            @endforeach
        </select>
    </label>

    <label class="block">
        <span class="text-sm text-slate-300">Primary branch</span>
        <select name="branch_id" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3">
            <option value="">Unassigned</option>
            @foreach($branches as $branch)
                <option value="{{ $branch->id }}" @selected((string) old('branch_id', $managedUser->branch_id ?? '') === (string) $branch->id)>{{ $branch->name }}</option>
            @endforeach
        </select>
    </label>

    <div class="md:col-span-2">
        <div class="mb-2 flex flex-wrap items-center justify-between gap-3">
            <div>
                <span class="text-sm text-slate-300">User location <span class="text-slate-500">(optional)</span></span>
                <p class="mt-1 text-xs text-slate-500">Click the map or drag the marker to store the user's working/base location.</p>
            </div>
            <button id="clear-user-location" type="button" class="rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold text-slate-300 hover:bg-white/10">Clear location</button>
        </div>

        <input id="user-latitude" type="hidden" name="latitude" value="{{ $selectedLatitude }}">
        <input id="user-longitude" type="hidden" name="longitude" value="{{ $selectedLongitude }}">

        <div id="user-location-map" class="h-[360px] overflow-hidden rounded-2xl border border-white/10 bg-slate-950"></div>
        <div class="mt-2 flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500">
            <span id="user-location-status">
                @if($selectedLatitude !== '' && $selectedLongitude !== '')
                    Selected: {{ $selectedLatitude }}, {{ $selectedLongitude }}
                @else
                    No location selected yet.
                @endif
            </span>
            <span>Coordinates are saved automatically from the map.</span>
        </div>

        @error('latitude')<p class="mt-2 text-sm text-rose-300">{{ $message }}</p>@enderror
        @error('longitude')<p class="mt-2 text-sm text-rose-300">{{ $message }}</p>@enderror
    </div>

    <label class="block">
        <span class="text-sm text-slate-300">Password {{ $editing ? '(leave blank to keep current)' : '' }}</span>
        <input type="password" name="password" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3" {{ $editing ? '' : 'required' }}>
    </label>

    <label class="block">
        <span class="text-sm text-slate-300">Confirm password</span>
        <input type="password" name="password_confirmation" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3" {{ $editing ? '' : 'required' }}>
    </label>

    <label class="flex items-center gap-3 md:col-span-2">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" class="h-5 w-5 rounded" @checked((bool) old('is_active', $managedUser->is_active ?? true))>
        <span>
            <span class="block font-medium">Active account</span>
            <span class="block text-sm text-slate-400">Inactive users cannot sign in.</span>
        </span>
    </label>
</div>

<script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
<script>
(() => {
    const mapElement = document.getElementById('user-location-map');
    const latitudeInput = document.getElementById('user-latitude');
    const longitudeInput = document.getElementById('user-longitude');
    const status = document.getElementById('user-location-status');
    const clearButton = document.getElementById('clear-user-location');

    if (!mapElement || !latitudeInput || !longitudeInput || typeof L === 'undefined') return;

    const defaultCenter = [34.5553, 69.2075];
    const initialLatitude = Number.parseFloat(latitudeInput.value);
    const initialLongitude = Number.parseFloat(longitudeInput.value);
    const hasInitial = Number.isFinite(initialLatitude) && Number.isFinite(initialLongitude);
    const initialCenter = hasInitial ? [initialLatitude, initialLongitude] : defaultCenter;

    const map = L.map(mapElement).setView(initialCenter, hasInitial ? 15 : 11);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors',
    }).addTo(map);

    let marker = null;

    const updateLocation = (latlng) => {
        const lat = Number(latlng.lat.toFixed(7));
        const lng = Number(latlng.lng.toFixed(7));

        latitudeInput.value = lat;
        longitudeInput.value = lng;
        status.textContent = 'Selected: ' + lat.toFixed(7) + ', ' + lng.toFixed(7);

        if (!marker) {
            marker = L.marker([lat, lng], {draggable: true}).addTo(map);
            marker.on('dragend', () => updateLocation(marker.getLatLng()));
        } else {
            marker.setLatLng([lat, lng]);
        }
    };

    if (hasInitial) {
        updateLocation({lat: initialLatitude, lng: initialLongitude});
    }

    map.on('click', (event) => updateLocation(event.latlng));

    clearButton?.addEventListener('click', () => {
        latitudeInput.value = '';
        longitudeInput.value = '';
        status.textContent = 'No location selected yet.';

        if (marker) {
            map.removeLayer(marker);
            marker = null;
        }
    });

    setTimeout(() => map.invalidateSize(), 0);
})();
</script>
