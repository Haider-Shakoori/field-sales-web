<x-layouts.app>
    <div class="mb-6"><h1 class="text-2xl font-bold">{{ $device->device_model ?? 'Registered device' }}</h1><p class="mt-1 text-sm text-slate-400">{{ $device->installation_uuid }}</p></div>
    <div class="grid gap-5 lg:grid-cols-2">
        <section class="rounded-2xl border border-white/10 bg-slate-900 p-5">
            <dl class="grid grid-cols-2 gap-4 text-sm">
                <dt class="text-slate-400">Salesman</dt><dd>{{ $device->salesman?->full_name ?? '—' }}</dd>
                <dt class="text-slate-400">User</dt><dd>{{ $device->user?->email ?? '—' }}</dd>
                <dt class="text-slate-400">Device UUID</dt><dd class="break-all">{{ $device->device_uuid }}</dd>
                <dt class="text-slate-400">Platform</dt><dd>{{ $device->platform }} {{ $device->os_version }}</dd>
                <dt class="text-slate-400">App version</dt><dd>{{ $device->app_version ?? '—' }}</dd>
                <dt class="text-slate-400">Registered</dt><dd>{{ $device->registered_at?->toDateTimeString() ?? '—' }}</dd>
                <dt class="text-slate-400">Last seen</dt><dd>{{ $device->last_seen_at?->toDateTimeString() ?? '—' }}</dd>
                <dt class="text-slate-400">Status</dt><dd>{{ $device->isRevoked() ? 'Revoked' : 'Active' }}</dd>
            </dl>
        </section>
        <section class="rounded-2xl border border-white/10 bg-slate-900 p-5">
            @if($device->isRevoked())
                <h2 class="font-semibold text-red-300">Revoked</h2>
                <p class="mt-2 text-sm text-slate-400">Revoked {{ $device->revoked_at?->toDateTimeString() }} by {{ $device->revoker?->name ?? 'system' }}.</p>
                @if($device->revocation_reason)<p class="mt-3 rounded-xl bg-slate-950 p-3 text-sm">{{ $device->revocation_reason }}</p>@endif
            @elseif(auth()->user()->hasPermission('sales-team:manage'))
                <h2 class="font-semibold">Revoke device</h2>
                <p class="mt-2 text-sm text-slate-400">Revoking stops this installation from using its current token. The salesman can register another device afterward.</p>
                <form method="POST" action="{{ route('admin.devices.revoke', $device) }}" class="mt-4 space-y-3">
                    @csrf
                    <textarea name="reason" rows="3" placeholder="Reason (optional)" class="w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3"></textarea>
                    <button class="rounded-xl bg-red-500/20 px-4 py-2.5 font-semibold text-red-200" onclick="return confirm('Revoke this device?')">Revoke device</button>
                </form>
            @endif
        </section>
    </div>
</x-layouts.app>
