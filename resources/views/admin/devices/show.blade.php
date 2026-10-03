<x-layouts.app>
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

    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="text-2xl font-bold">{{ $device->device_model ?? 'Registered device' }}</h1>
                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $healthClass }}">{{ str($health)->replace('_', ' ')->title() }}</span>
            </div>
            <p class="mt-1 text-sm text-slate-400">{{ $device->installation_uuid }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.mobile-diagnostics.index', ['user' => $device->user?->email]) }}" class="rounded-xl bg-white/10 px-4 py-2.5 text-sm font-semibold hover:bg-white/15">Diagnostics</a>
            <a href="{{ route('admin.devices.index') }}" class="rounded-xl bg-white/10 px-4 py-2.5 text-sm font-semibold hover:bg-white/15">Back to devices</a>
        </div>
    </div>

    @if(session('status'))
        <div class="mb-5 rounded-xl border border-indigo-500/20 bg-indigo-500/10 p-4 text-sm text-indigo-200">
            {{ session('status') }}
        </div>
    @endif

    @if(session('device_test_result'))
        @php
            $testResult = session('device_test_result');
        @endphp
        <section class="mb-5 rounded-2xl border border-white/10 bg-slate-900 p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="font-semibold">{{ session('device_test_name', 'Device test') }}</h2>
                <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $testResult['status'] === 'passed' ? 'bg-emerald-500/15 text-emerald-300' : ($testResult['status'] === 'warning' ? 'bg-amber-500/15 text-amber-300' : 'bg-red-500/15 text-red-300') }}">
                    {{ str($testResult['status'])->title() }}
                </span>
            </div>
            <div class="mt-4 grid gap-2 md:grid-cols-2">
                @foreach($testResult['checks'] as $check)
                    <div class="rounded-xl border border-white/10 bg-slate-950/60 p-3 text-sm">
                        <div class="flex items-center justify-between gap-3">
                            <span class="font-medium">{{ $check['name'] }}</span>
                            <span class="{{ $check['passed'] ? 'text-emerald-300' : ($check['severity'] === 'critical' ? 'text-red-300' : 'text-amber-300') }}">
                                {{ $check['passed'] ? 'Pass' : str($check['severity'])->title() }}
                            </span>
                        </div>
                        <p class="mt-1 text-xs text-slate-400">{{ $check['detail'] }}</p>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <div class="grid gap-5 xl:grid-cols-3">
        <section class="rounded-2xl border border-white/10 bg-slate-900 p-5">
            <h2 class="font-semibold">Device identity</h2>
            <dl class="mt-4 grid grid-cols-2 gap-4 text-sm">
                <dt class="text-slate-400">Salesman</dt><dd>{{ $device->salesman?->full_name ?? '—' }}</dd>
                <dt class="text-slate-400">User</dt><dd class="break-all">{{ $device->user?->email ?? '—' }}</dd>
                <dt class="text-slate-400">Device UUID</dt><dd class="break-all">{{ $device->device_uuid }}</dd>
                <dt class="text-slate-400">Platform</dt><dd>{{ trim(($device->platform ?? '').' '.($device->os_version ?? $device->android_version ?? '')) ?: '—' }}</dd>
                <dt class="text-slate-400">Manufacturer</dt><dd>{{ $device->manufacturer ?? '—' }}</dd>
                <dt class="text-slate-400">App version</dt><dd>{{ $device->app_version ?? '—' }}</dd>
                <dt class="text-slate-400">Version policy</dt><dd class="{{ $mobilePolicy->isSupported($device->app_version) ? '' : 'text-red-300' }}">{{ $mobilePolicy->isSupported($device->app_version) ? 'Supported' : 'Update required' }} · minimum {{ $mobilePolicy->minimumVersion() }}</dd>
                <dt class="text-slate-400">Device role</dt><dd>{{ $device->is_primary ? 'Primary' : 'Secondary' }}</dd>
                <dt class="text-slate-400">Approval</dt><dd>{{ str($device->approval_status ?? 'approved')->title() }}</dd>
                <dt class="text-slate-400">Management status</dt><dd>{{ str($device->management_status ?? 'active')->title() }}</dd>
                <dt class="text-slate-400">Registered</dt><dd>{{ $device->registered_at?->toDateTimeString() ?? '—' }}</dd>
                <dt class="text-slate-400">Last seen</dt><dd>{{ $device->last_seen_at?->toDateTimeString() ?? '—' }}</dd>
                <dt class="text-slate-400">Health reported</dt><dd>{{ $device->health_reported_at?->toDateTimeString() ?? '—' }}</dd>
            </dl>
        </section>

        <section class="rounded-2xl border border-white/10 bg-slate-900 p-5">
            <div class="flex items-center justify-between gap-3">
                <h2 class="font-semibold">Health summary</h2>
                <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $healthClass }}">{{ str($health)->title() }}</span>
            </div>
            @if(empty($device->health_issues))
                <p class="mt-4 rounded-xl bg-emerald-500/10 p-4 text-sm text-emerald-200">
                    {{ $device->health_reported_at ? 'No current health warnings were reported.' : 'No health heartbeat has been received from this device yet.' }}
                </p>
            @else
                <div class="mt-4 space-y-2">
                    @foreach($device->health_issues as $issue)
                        @php
                            $severity = $issue['severity'] ?? 'info';
                            $issueClass = match ($severity) {
                                'critical' => 'border-red-500/25 bg-red-500/10 text-red-200',
                                'warning' => 'border-amber-500/25 bg-amber-500/10 text-amber-200',
                                default => 'border-sky-500/25 bg-sky-500/10 text-sky-200',
                            };
                        @endphp
                        <div class="rounded-xl border p-3 text-sm {{ $issueClass }}">
                            <div class="text-xs font-semibold uppercase tracking-wide opacity-70">{{ $severity }}</div>
                            <div class="mt-1">{{ $issue['message'] ?? $issue['code'] ?? 'Device health issue' }}</div>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="rounded-2xl border border-white/10 bg-slate-900 p-5">
            <h2 class="font-semibold">Battery & Android background</h2>
            <dl class="mt-4 grid grid-cols-2 gap-4 text-sm">
                <dt class="text-slate-400">Battery</dt><dd>{{ $device->battery_level === null ? '—' : $device->battery_level.'%' }}</dd>
                <dt class="text-slate-400">Charging</dt><dd>{{ $device->is_charging === null ? 'Unknown' : ($device->is_charging ? 'Yes' : 'No') }}</dd>
                <dt class="text-slate-400">Battery saver</dt><dd>{{ $device->power_save_mode === null ? 'Unknown' : ($device->power_save_mode ? 'On' : 'Off') }}</dd>
                <dt class="text-slate-400">Optimization exemption</dt><dd>{{ $device->battery_optimization_exempt === null ? 'Unknown' : ($device->battery_optimization_exempt ? 'Exempt' : 'Restricted') }}</dd>
                <dt class="text-slate-400">Background restricted</dt><dd>{{ $device->background_restricted === null ? 'Unknown' : ($device->background_restricted ? 'Yes' : 'No') }}</dd>
                <dt class="text-slate-400">Network</dt><dd>{{ $device->network_type ?? '—' }}</dd>
            </dl>
        </section>

        <section class="rounded-2xl border border-white/10 bg-slate-900 p-5">
            <h2 class="font-semibold">Location & tracking</h2>
            <dl class="mt-4 grid grid-cols-2 gap-4 text-sm">
                <dt class="text-slate-400">Work day</dt><dd>{{ $device->workday_active ? 'Active' : 'Inactive' }}</dd>
                <dt class="text-slate-400">Location services</dt><dd>{{ $device->location_services_enabled === null ? 'Unknown' : ($device->location_services_enabled ? 'Enabled' : 'Disabled') }}</dd>
                <dt class="text-slate-400">Location permission</dt><dd>{{ $device->location_permission ?? '—' }}</dd>
                <dt class="text-slate-400">Background permission</dt><dd>{{ $device->background_location_permission ?? '—' }}</dd>
                <dt class="text-slate-400">Background tracking</dt><dd>{{ $device->background_tracking_active === null ? 'Unknown' : ($device->background_tracking_active ? 'Active' : 'Inactive') }}</dd>
                <dt class="text-slate-400">Last GPS fix</dt><dd>{{ $device->last_gps_fix_at?->toDateTimeString() ?? '—' }}</dd>
                <dt class="text-slate-400">Mock-location signal</dt><dd>{{ $device->mock_location_detected ? 'Detected' : 'Not detected' }}</dd>
            </dl>
        </section>

        <section class="rounded-2xl border border-white/10 bg-slate-900 p-5">
            <h2 class="font-semibold">Sync & storage</h2>
            <dl class="mt-4 grid grid-cols-2 gap-4 text-sm">
                <dt class="text-slate-400">Pending</dt><dd>{{ number_format((int) $device->pending_sync_count) }}</dd>
                <dt class="text-slate-400">Failed</dt><dd>{{ number_format((int) $device->failed_sync_count) }}</dd>
                <dt class="text-slate-400">Blocked</dt><dd>{{ number_format((int) $device->blocked_sync_count) }}</dd>
                <dt class="text-slate-400">Last sync</dt><dd>{{ $device->last_sync_at?->toDateTimeString() ?? '—' }}</dd>
                <dt class="text-slate-400">Free storage</dt><dd>{{ $device->storage_free_mb === null ? '—' : number_format($device->storage_free_mb).' MB' }}</dd>
                <dt class="text-slate-400">Total storage</dt><dd>{{ $device->storage_total_mb === null ? '—' : number_format($device->storage_total_mb).' MB' }}</dd>
            </dl>
        </section>

        <section class="rounded-2xl border border-white/10 bg-slate-900 p-5">
            <h2 class="font-semibold">Permissions & integrity signals</h2>
            <dl class="mt-4 grid grid-cols-2 gap-4 text-sm">
                <dt class="text-slate-400">Notifications</dt><dd>{{ $device->notification_permission ?? '—' }}</dd>
                <dt class="text-slate-400">Physical device</dt><dd>{{ $device->is_physical_device === null ? 'Unknown' : ($device->is_physical_device ? 'Yes' : 'No / emulator') }}</dd>
                <dt class="text-slate-400">Root signal</dt><dd>{{ $device->root_signal_detected ? 'Detected' : 'Not detected' }}</dd>
            </dl>
            <p class="mt-4 text-xs leading-5 text-slate-500">Integrity signals are diagnostic indicators only. They are not treated as proof of misuse.</p>
        </section>
    </div>

    @php
        $showOptionalActions = auth()->user()->hasPermission('sales-team:manage')
            && (
                $deviceSettings['approval_required']
                || $deviceSettings['lost_device_workflow_enabled']
                || $deviceSettings['remote_diagnostics_enabled']
                || $deviceSettings['push_test_enabled']
                || $deviceSettings['gps_background_test_enabled']
                || $deviceSettings['sync_test_enabled']
            );
        $lostDeviceStates = [
            'lost' => 'Mark as lost',
            'stolen' => 'Mark as stolen',
        ];
    @endphp

    @if($showOptionalActions)
        <section class="mt-5 rounded-2xl border border-white/10 bg-slate-900 p-5">
            <div>
                <h2 class="font-semibold">Optional device actions</h2>
                <p class="mt-1 text-sm text-slate-400">Only controls enabled in company settings are shown here.</p>
            </div>

            <div class="mt-4 flex flex-wrap gap-2">
                @if($deviceSettings['approval_required'] && ! $device->isApproved())
                    <form method="POST" action="{{ route('admin.devices.approve', $device) }}">
                        @csrf
                        <button class="rounded-xl bg-emerald-500/20 px-4 py-2.5 text-sm font-semibold text-emerald-200">Approve device</button>
                    </form>
                @endif

                @if($deviceSettings['remote_diagnostics_enabled'] && ! $device->isRevoked())
                    <form method="POST" action="{{ route('admin.devices.request-diagnostics', $device) }}">
                        @csrf
                        <button class="rounded-xl bg-indigo-500/20 px-4 py-2.5 text-sm font-semibold text-indigo-200">Request diagnostics</button>
                    </form>
                @endif

                @if($deviceSettings['push_test_enabled'] && ! $device->isRevoked())
                    <form method="POST" action="{{ route('admin.devices.test-push', $device) }}">
                        @csrf
                        <button class="rounded-xl bg-sky-500/20 px-4 py-2.5 text-sm font-semibold text-sky-200">Test push notification</button>
                    </form>
                @endif

                @if($deviceSettings['gps_background_test_enabled'])
                    <form method="POST" action="{{ route('admin.devices.test-gps-background', $device) }}">
                        @csrf
                        <button class="rounded-xl bg-white/10 px-4 py-2.5 text-sm font-semibold text-slate-200">Test GPS & background</button>
                    </form>
                @endif

                @if($deviceSettings['sync_test_enabled'])
                    <form method="POST" action="{{ route('admin.devices.test-sync', $device) }}">
                        @csrf
                        <button class="rounded-xl bg-white/10 px-4 py-2.5 text-sm font-semibold text-slate-200">Test sync</button>
                    </form>
                @endif
            </div>

            @if($deviceSettings['lost_device_workflow_enabled'] && ! $device->isRevoked())
                <div class="mt-5 border-t border-white/10 pt-5">
                    <h3 class="font-medium">Lost or stolen device</h3>
                    <p class="mt-1 text-xs leading-5 text-slate-400">These actions immediately revoke the selected device and invalidate its mobile token.</p>
                    <div class="mt-3 grid gap-3 md:grid-cols-2">
                        @foreach($lostDeviceStates as $status => $label)
                            <form method="POST" action="{{ route('admin.devices.management-status', $device) }}" class="rounded-xl border border-white/10 bg-slate-950/50 p-3">
                                @csrf
                                <input type="hidden" name="status" value="{{ $status }}">
                                <textarea name="reason" rows="2" placeholder="Reason or note (optional)" class="w-full rounded-lg border border-white/10 bg-slate-950 px-3 py-2 text-sm"></textarea>
                                <button class="mt-2 rounded-lg {{ $status === 'stolen' ? 'bg-red-500/20 text-red-200' : 'bg-amber-500/20 text-amber-200' }} px-3 py-2 text-sm font-semibold" onclick="return confirm('This will immediately revoke the device. Continue?')">{{ $label }}</button>
                            </form>
                        @endforeach
                    </div>
                </div>
            @endif
        </section>
    @endif

    @if($recentDiagnostics->isNotEmpty())
        <section class="mt-5 rounded-2xl border border-white/10 bg-slate-900 p-5">
            <div class="flex items-center justify-between gap-3">
                <h2 class="font-semibold">Recent mobile diagnostics</h2>
                <a href="{{ route('admin.mobile-diagnostics.index', ['user' => $device->user?->email]) }}" class="text-sm font-semibold text-indigo-300 hover:text-indigo-200">View all</a>
            </div>
            <div class="mt-4 divide-y divide-white/10">
                @foreach($recentDiagnostics as $diagnostic)
                    <div class="py-3 first:pt-0 last:pb-0">
                        <div class="flex flex-wrap items-center gap-2 text-xs">
                            <span class="rounded-full bg-white/10 px-2 py-1">{{ str($diagnostic->severity)->title() }}</span>
                            <span class="text-slate-400">{{ $diagnostic->area }}</span>
                            <span class="text-slate-500">{{ $diagnostic->occurred_at?->diffForHumans() }}</span>
                        </div>
                        <p class="mt-2 text-sm text-slate-200">{{ $diagnostic->message }}</p>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @if($deviceSettings['activity_history_enabled'])
        <section class="mt-5 rounded-2xl border border-white/10 bg-slate-900 p-5">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="font-semibold">Device activity history</h2>
                    <p class="mt-1 text-xs text-slate-400">Only important device events are stored while this optional feature is enabled.</p>
                </div>
                <span class="text-xs text-slate-500">{{ $deviceActivity->count() }} recent event(s)</span>
            </div>
            <div class="mt-4 divide-y divide-white/10">
                @forelse($deviceActivity as $entry)
                    <div class="py-3 first:pt-0 last:pb-0">
                        <div class="flex flex-wrap items-center gap-2 text-xs">
                            <span class="rounded-full bg-white/10 px-2 py-1">{{ str($entry->event)->replace('.', ' ')->title() }}</span>
                            <span class="text-slate-500">{{ $entry->occurred_at?->diffForHumans() }}</span>
                            @if($entry->actor)<span class="text-slate-500">by {{ $entry->actor->name }}</span>@endif
                        </div>
                        @if(!empty($entry->context))
                            <p class="mt-2 break-words text-xs text-slate-400">{{ json_encode($entry->context, JSON_UNESCAPED_SLASHES) }}</p>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-slate-400">No device activity has been recorded yet.</p>
                @endforelse
            </div>
        </section>
    @endif

    <section class="mt-5 rounded-2xl border border-white/10 bg-slate-900 p-5">
        @if($device->isRevoked())
            <h2 class="font-semibold text-red-300">Revoked</h2>
            <p class="mt-2 text-sm text-slate-400">Revoked {{ $device->revoked_at?->toDateTimeString() }} by {{ $device->revoker?->name ?? 'system' }}.</p>
            @if($device->revocation_reason)<p class="mt-3 rounded-xl bg-slate-950 p-3 text-sm">{{ $device->revocation_reason }}</p>@endif
        @elseif(auth()->user()->hasPermission('sales-team:manage'))
            <div class="mb-6 border-b border-white/10 pb-6">
                <h2 class="font-semibold">Remote sign-out</h2>
                <p class="mt-2 text-sm text-slate-400">Invalidate this device's current mobile token without revoking the installation. The user must sign in again on the same phone.</p>
                <form method="POST" action="{{ route('admin.devices.force-logout', $device) }}" class="mt-4">
                    @csrf
                    <button class="rounded-xl bg-amber-500/20 px-4 py-2.5 font-semibold text-amber-200" onclick="return confirm('Sign this device out remotely?')">Require reauthentication</button>
                </form>
            </div>
            <h2 class="font-semibold">Revoke device</h2>
            <p class="mt-2 text-sm text-slate-400">Revoking stops this installation from using its current token. The user can register another device afterward.</p>
            <form method="POST" action="{{ route('admin.devices.revoke', $device) }}" class="mt-4 max-w-xl space-y-3">
                @csrf
                <textarea name="reason" rows="3" placeholder="Reason (optional)" class="w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3"></textarea>
                <button class="rounded-xl bg-red-500/20 px-4 py-2.5 font-semibold text-red-200" onclick="return confirm('Revoke this device?')">Revoke device</button>
            </form>
        @endif
    </section>
</x-layouts.app>
