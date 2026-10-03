<x-layouts.app>
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold">Registered devices</h1>
            <p class="mt-1 text-sm text-slate-400">Device-bound mobile sessions with live health, permissions, sync and tracking diagnostics.</p>
        </div>
        <a href="{{ route('admin.mobile-diagnostics.index') }}" class="inline-flex items-center justify-center rounded-xl bg-white/10 px-4 py-2.5 text-sm font-semibold text-slate-200 hover:bg-white/15">
            Mobile diagnostics
        </a>
    </div>

    <div class="overflow-hidden rounded-2xl border border-white/10 bg-slate-900">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-white/5 text-slate-300">
                    <tr>
                        <th class="px-5 py-3">User</th>
                        <th class="px-5 py-3">Device</th>
                        <th class="px-5 py-3">Health</th>
                        <th class="px-5 py-3">Battery</th>
                        <th class="px-5 py-3">Location</th>
                        <th class="px-5 py-3">Sync</th>
                        <th class="px-5 py-3">Last seen</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/10">
                @forelse($devices as $device)
                    @php
                        $health = $device->effectiveHealthStatus();
                        $healthClass = match ($health) {
                            'healthy' => 'bg-emerald-500/15 text-emerald-300',
                            'warning' => 'bg-amber-500/15 text-amber-300',
                            'critical' => 'bg-red-500/15 text-red-300',
                            'stale' => 'bg-orange-500/15 text-orange-300',
                            'revoked' => 'bg-slate-700 text-slate-300',
                            default => 'bg-slate-700/70 text-slate-300',
                        };
                    @endphp
                    <tr class="align-top hover:bg-white/[0.025]">
                        <td class="px-5 py-4">
                            <div class="font-medium text-slate-100">{{ $device->salesman?->full_name ?? $device->user?->name ?? '—' }}</div>
                            <div class="mt-1 text-xs text-slate-500">{{ $device->user?->email ?? '—' }}</div>
                        </td>
                        <td class="px-5 py-4">
                            <div>{{ $device->device_model ?? 'Mobile device' }}</div>
                            <div class="mt-1 text-xs text-slate-500">{{ trim(($device->platform ?? '').' '.($device->os_version ?? $device->android_version ?? '')) ?: '—' }}</div>
                            <div class="mt-1 text-xs {{ $mobilePolicy->isSupported($device->app_version) ? 'text-slate-500' : 'text-red-300' }}">
                                App {{ $device->app_version ?? '—' }} · {{ $mobilePolicy->isSupported($device->app_version) ? 'supported' : 'update required' }}
                            </div>
                            @if($deviceSettings['secondary_device_enabled'] || $deviceSettings['approval_required'] || $deviceSettings['lost_device_workflow_enabled'])
                                <div class="mt-2 flex flex-wrap gap-1.5 text-[11px]">
                                    @if($deviceSettings['secondary_device_enabled'])
                                        <span class="rounded-full bg-white/10 px-2 py-0.5">{{ $device->is_primary ? 'Primary' : 'Secondary' }}</span>
                                    @endif
                                    @if($deviceSettings['approval_required'])
                                        <span class="rounded-full {{ $device->isApproved() ? 'bg-emerald-500/10 text-emerald-300' : 'bg-amber-500/10 text-amber-300' }} px-2 py-0.5">{{ str($device->approval_status)->title() }}</span>
                                    @endif
                                    @if($deviceSettings['lost_device_workflow_enabled'] && ($device->management_status ?? 'active') !== 'active')
                                        <span class="rounded-full bg-red-500/10 px-2 py-0.5 text-red-300">{{ str($device->management_status)->title() }}</span>
                                    @endif
                                </div>
                            @endif
                        </td>
                        <td class="px-5 py-4">
                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $healthClass }}">{{ str($health)->replace('_', ' ')->title() }}</span>
                            <div class="mt-2 text-xs text-slate-500">{{ $device->health_reported_at?->diffForHumans() ?? 'No health report yet' }}</div>
                        </td>
                        <td class="px-5 py-4">
                            <div>{{ $device->battery_level === null ? '—' : $device->battery_level.'%' }}</div>
                            <div class="mt-1 text-xs text-slate-500">{{ $device->is_charging ? 'Charging' : 'Not charging' }}</div>
                        </td>
                        <td class="px-5 py-4">
                            <div>{{ $device->location_services_enabled === null ? 'Unknown' : ($device->location_services_enabled ? 'GPS on' : 'GPS off') }}</div>
                            <div class="mt-1 text-xs text-slate-500">{{ $device->background_tracking_active === null ? 'Tracking unknown' : ($device->background_tracking_active ? 'Background tracking active' : 'Background tracking inactive') }}</div>
                        </td>
                        <td class="px-5 py-4">
                            <div>{{ number_format((int) $device->pending_sync_count) }} pending</div>
                            <div class="mt-1 text-xs {{ $device->blocked_sync_count > 0 ? 'text-red-300' : 'text-slate-500' }}">
                                {{ number_format((int) $device->failed_sync_count) }} failed · {{ number_format((int) $device->blocked_sync_count) }} blocked
                            </div>
                        </td>
                        <td class="px-5 py-4">
                            <div>{{ $device->last_seen_at?->diffForHumans() ?? 'Never' }}</div>
                            <div class="mt-1 text-xs text-slate-500">{{ $device->network_type ?? 'Network unknown' }}</div>
                        </td>
                        <td class="px-5 py-4 text-right">
                            <a href="{{ route('admin.devices.show', $device) }}" class="rounded-lg bg-white/10 px-3 py-2 font-semibold hover:bg-white/15">View</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-5 py-10 text-center text-slate-400">No devices registered.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-5">{{ $devices->links() }}</div>
</x-layouts.app>
