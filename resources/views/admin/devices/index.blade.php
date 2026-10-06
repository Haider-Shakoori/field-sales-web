<x-layouts.app>
    <div class="fp-page-header">
        <div>
            <div class="fp-page-eyebrow">Mobile operations</div>
            <h1 class="fp-page-title">Registered devices</h1>
            <p class="fp-page-subtitle">Monitor field devices, background tracking, connectivity and sync reliability from one place.</p>
        </div>
        <div class="fp-toolbar">
            <a href="{{ route('tracking.edit') }}" class="fp-toolbar-chip hover:bg-white/10">Tracking policy</a>
            <a href="{{ route('admin.mobile-diagnostics.index') }}" class="fp-toolbar-chip bg-indigo-500/15 text-indigo-200 hover:bg-indigo-500/25">Mobile diagnostics</a>
        </div>
    </div>

    <section class="fp-kpi-grid mb-6">
        <div class="fp-kpi-card">
            <div class="fp-kpi-label">Active devices</div>
            <div class="fp-kpi-value">{{ $syncSummary['active'] }}</div>
            <div class="fp-kpi-meta">Registered and not revoked</div>
        </div>
        <div class="fp-kpi-card">
            <div class="fp-kpi-label">Sync healthy</div>
            <div class="fp-kpi-value">{{ $syncSummary['healthy'] }}</div>
            <div class="fp-kpi-meta text-emerald-300">Current with no queue errors</div>
        </div>
        <div class="fp-kpi-card">
            <div class="fp-kpi-label">Need attention</div>
            <div class="fp-kpi-value">{{ $syncSummary['attention'] }}</div>
            <div class="fp-kpi-meta {{ $syncSummary['attention'] > 0 ? 'text-amber-300' : 'text-emerald-300' }}">
                {{ $syncSummary['attention'] > 0 ? 'Stale or carrying sync work' : 'No device requires attention' }}
            </div>
        </div>
        <div class="fp-kpi-card">
            <div class="fp-kpi-label">Queued work</div>
            <div class="fp-kpi-value">{{ $syncSummary['pending'] }}</div>
            <div class="fp-kpi-meta">
                {{ $syncSummary['failed'] }} failed ·
                <span class="{{ $syncSummary['blocked'] > 0 ? 'text-red-300' : '' }}">{{ $syncSummary['blocked'] }} blocked</span>
            </div>
        </div>
    </section>

    <section class="overflow-hidden fp-surface">
        <div class="fp-section-heading border-b border-white/10 px-5 py-4">
            <div>
                <h2 class="fp-section-title">Device fleet</h2>
                <p class="fp-section-copy">Live health reports update battery, GPS, background tracking and local sync queue state.</p>
            </div>
            <span class="fp-badge bg-white/5 text-slate-300">{{ $devices->total() }} registered</span>
        </div>

        <div class="overflow-x-auto">
            <table class="fp-table min-w-[1100px]">
                <thead class="bg-white/5">
                    <tr>
                        <th class="px-5 py-3">User</th>
                        <th class="px-5 py-3">Device</th>
                        <th class="px-5 py-3">Health</th>
                        <th class="px-5 py-3">Tracking</th>
                        <th class="px-5 py-3">Sync</th>
                        <th class="px-5 py-3">Last contact</th>
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
                        $syncNeedsAttention = (int) $device->pending_sync_count > 0
                            || (int) $device->failed_sync_count > 0
                            || (int) $device->blocked_sync_count > 0;
                    @endphp
                    <tr class="align-top">
                        <td class="px-5 py-4">
                            <div class="font-semibold text-slate-100">{{ $device->salesman?->full_name ?? $device->user?->name ?? '—' }}</div>
                            <div class="mt-1 text-xs text-slate-500">{{ $device->user?->email ?? '—' }}</div>
                        </td>
                        <td class="px-5 py-4">
                            <div class="font-medium">{{ $device->device_model ?? 'Mobile device' }}</div>
                            <div class="mt-1 text-xs text-slate-500">{{ trim(($device->platform ?? '').' '.($device->os_version ?? $device->android_version ?? '')) ?: '—' }}</div>
                            <div class="mt-1 text-xs {{ $mobilePolicy->isSupported($device->app_version) ? 'text-slate-500' : 'text-red-300' }}">
                                App {{ $device->app_version ?? '—' }} · {{ $mobilePolicy->isSupported($device->app_version) ? 'supported' : 'update required' }}
                            </div>
                        </td>
                        <td class="px-5 py-4">
                            <span class="fp-badge {{ $healthClass }}">{{ str($health)->replace('_', ' ')->title() }}</span>
                            <div class="mt-2 text-xs text-slate-500">{{ $device->battery_level === null ? 'Battery unknown' : $device->battery_level.'% battery' }}</div>
                            <div class="mt-1 text-xs text-slate-500">{{ $device->health_reported_at?->diffForHumans() ?? 'No report yet' }}</div>
                        </td>
                        <td class="px-5 py-4">
                            <div class="flex items-center gap-2">
                                <span class="h-2 w-2 rounded-full {{ $device->location_services_enabled ? 'bg-emerald-400' : 'bg-slate-600' }}"></span>
                                <span>{{ $device->location_services_enabled === null ? 'GPS unknown' : ($device->location_services_enabled ? 'GPS on' : 'GPS off') }}</span>
                            </div>
                            <div class="mt-2 text-xs {{ $device->background_tracking_active ? 'text-emerald-300' : 'text-slate-500' }}">
                                {{ $device->background_tracking_active === null ? 'Background status unknown' : ($device->background_tracking_active ? 'Background tracking active' : 'Background tracking inactive') }}
                            </div>
                        </td>
                        <td class="px-5 py-4">
                            <div class="font-semibold {{ $syncNeedsAttention ? 'text-amber-300' : 'text-emerald-300' }}">
                                {{ number_format((int) $device->pending_sync_count) }} pending
                            </div>
                            <div class="mt-1 text-xs {{ $device->blocked_sync_count > 0 ? 'text-red-300' : 'text-slate-500' }}">
                                {{ number_format((int) $device->failed_sync_count) }} failed · {{ number_format((int) $device->blocked_sync_count) }} blocked
                            </div>
                            <div class="mt-2 text-xs text-slate-500">
                                Last sync {{ $device->last_sync_at?->diffForHumans() ?? 'never' }}
                            </div>
                        </td>
                        <td class="px-5 py-4">
                            <div>{{ $device->last_seen_at?->diffForHumans() ?? 'Never' }}</div>
                            <div class="mt-1 text-xs text-slate-500">{{ $device->network_type ?? 'Network unknown' }}</div>
                        </td>
                        <td class="px-5 py-4 text-right">
                            <a href="{{ route('admin.devices.show', $device) }}" class="fp-btn-secondary px-3 py-2">Inspect</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-12 text-center text-slate-400">No devices registered yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div class="mt-5">{{ $devices->links() }}</div>
</x-layouts.app>
