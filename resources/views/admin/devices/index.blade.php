<x-layouts.app>
    <div class="mb-6">
        <h1 class="text-2xl font-bold">Registered devices</h1>
        <p class="mt-1 text-sm text-slate-400">Device-bound mobile sessions. Revocation invalidates the matching mobile token.</p>
    </div>
    <div class="overflow-hidden rounded-2xl border border-white/10 bg-slate-900">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-white/5 text-slate-300"><tr><th class="px-5 py-3">Salesman</th><th class="px-5 py-3">Device</th><th class="px-5 py-3">App</th><th class="px-5 py-3">Last seen</th><th class="px-5 py-3">Status</th><th class="px-5 py-3"></th></tr></thead>
                <tbody class="divide-y divide-white/10">
                @forelse($devices as $device)
                    <tr>
                        <td class="px-5 py-4">{{ $device->salesman?->full_name ?? $device->user?->name }}</td>
                        <td class="px-5 py-4"><div>{{ $device->device_model ?? 'Android device' }}</div><div class="text-xs text-slate-400">{{ $device->installation_uuid }}</div></td>
                        <td class="px-5 py-4">{{ $device->app_version ?? '—' }}</td>
                        <td class="px-5 py-4">{{ $device->last_seen_at?->diffForHumans() ?? 'Never' }}</td>
                        <td class="px-5 py-4">{{ $device->isRevoked() ? 'Revoked' : 'Active' }}</td>
                        <td class="px-5 py-4 text-right"><a href="{{ route('admin.devices.show', $device) }}" class="rounded-lg bg-white/10 px-3 py-2">View</a></td>
                    </tr>
                @empty<tr><td colspan="6" class="px-5 py-10 text-center text-slate-400">No devices registered.</td></tr>@endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-5">{{ $devices->links() }}</div>
</x-layouts.app>
