<x-layouts.app>
    <div class="mb-8 flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
        <div>
            <p class="font-semibold text-indigo-400">{{ $tenant->name }}</p>
            <h1 class="mt-1 text-3xl font-bold">Attendance, GPS & device policy</h1>
            <p class="mt-2 text-slate-400">
                Tenant timezone:
                <span class="text-slate-200">{{ $settings['timezone'] }}</span>
            </p>
        </div>
        <div class="rounded-2xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">
            Privacy-first · device controls are optional
        </div>
    </div>

    @if(session('status'))
        <div class="mb-6 rounded-xl border border-emerald-500/20 bg-emerald-500/10 p-4 text-emerald-300">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('tracking.update') }}" class="grid gap-6 lg:grid-cols-2">
        @csrf
        @method('PUT')

        <section class="space-y-5 rounded-3xl border border-white/10 bg-slate-900 p-6">
            <h2 class="text-xl font-bold">Work day</h2>

            <label class="block">
                Start mode
                <select name="work_session_start_mode" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 p-3">
                    <option value="manual" @selected($settings['work_session_start_mode']==='manual')>Manual</option>
                    <option value="automatic" @selected($settings['work_session_start_mode']==='automatic')>Automatic</option>
                </select>
            </label>

            <div class="grid grid-cols-2 gap-4">
                <label>
                    Start
                    <input type="time" name="workday_start_time" value="{{ $settings['workday_start_time'] }}" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 p-3">
                </label>
                <label>
                    End
                    <input type="time" name="workday_end_time" value="{{ $settings['workday_end_time'] }}" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 p-3">
                </label>
            </div>

            <input type="hidden" name="auto_end_session" value="0">
            <label class="flex items-center gap-3">
                <input type="checkbox" name="auto_end_session" value="1" @checked($settings['auto_end_session'])>
                <span>Automatically end outside the work window</span>
            </label>

            <label class="block">
                Privacy policy version
                <input name="privacy_policy_version" value="{{ $settings['privacy_policy_version'] }}" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 p-3">
            </label>
        </section>

        <section class="space-y-5 rounded-3xl border border-white/10 bg-slate-900 p-6">
            <h2 class="text-xl font-bold">Location tracking</h2>

            <input type="hidden" name="gps_tracking_enabled" value="0">
            <label class="flex items-center gap-3">
                <input type="checkbox" name="gps_tracking_enabled" value="1" @checked($settings['gps_tracking_enabled'])>
                <span>Enable continuous GPS during active sessions</span>
            </label>

            <label class="block">
                Moving interval (seconds)
                <input type="number" name="gps_moving_interval_seconds" value="{{ $settings['gps_moving_interval_seconds'] }}" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 p-3">
            </label>

            <label class="block">
                Stationary interval (seconds)
                <input type="number" name="gps_stationary_interval_seconds" value="{{ $settings['gps_stationary_interval_seconds'] }}" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 p-3">
            </label>

            <label class="block">
                Stale after (minutes)
                <input type="number" name="gps_stale_after_minutes" value="{{ $settings['gps_stale_after_minutes'] }}" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 p-3">
            </label>

            <div class="space-y-4 border-t border-white/10 pt-5">
                <h3 class="font-semibold">Idle-salesman alerts</h3>

                <input type="hidden" name="idle_alerts_enabled" value="0">
                <label class="flex items-center gap-3">
                    <input type="checkbox" name="idle_alerts_enabled" value="1" @checked($settings['idle_alerts_enabled'])>
                    <span>Automatically notify salesmen who remain idle during an active work session</span>
                </label>

                <div class="grid grid-cols-2 gap-4">
                    <label>
                        Notify after (minutes)
                        <input type="number" min="5" max="240" name="idle_alert_after_minutes" value="{{ $settings['idle_alert_after_minutes'] }}" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 p-3">
                    </label>
                    <label>
                        Repeat no sooner than (minutes)
                        <input type="number" min="15" max="480" name="idle_alert_repeat_minutes" value="{{ $settings['idle_alert_repeat_minutes'] }}" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 p-3">
                    </label>
                </div>

                <input type="hidden" name="idle_escalation_enabled" value="0">
                <label class="flex items-center gap-3">
                    <input type="checkbox" name="idle_escalation_enabled" value="1" @checked($settings['idle_escalation_enabled'])>
                    <span>Escalate persistent idle status to the assigned supervisor</span>
                </label>

                <label class="block">
                    Escalate after an additional (minutes)
                    <input type="number" min="15" max="480" name="idle_escalate_after_minutes" value="{{ $settings['idle_escalate_after_minutes'] }}" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 p-3">
                </label>
            </div>
        </section>

        <section class="space-y-6 rounded-3xl border border-white/10 bg-slate-900 p-6 lg:col-span-2">
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <h2 class="text-xl font-bold">Optional device controls</h2>
                    <span class="rounded-full bg-slate-800 px-2.5 py-1 text-xs text-slate-300">Off unless enabled</span>
                </div>
                <p class="mt-2 max-w-4xl text-sm leading-6 text-slate-400">
                    These controls are independent. Enable only the policies your company needs. Existing device behavior remains unchanged when these options are left off.
                </p>
            </div>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <div class="rounded-2xl border border-white/10 bg-slate-950/60 p-4">
                    <input type="hidden" name="device_restriction_enabled" value="0">
                    <label class="flex items-start gap-3">
                        <input type="checkbox" name="device_restriction_enabled" value="1" @checked($deviceSettings['device_restriction_enabled']) class="mt-1">
                        <span>
                            <span class="block font-semibold">Device restriction</span>
                            <span class="mt-1 block text-xs leading-5 text-slate-400">Keep mobile sessions bound to registered installations.</span>
                        </span>
                    </label>
                </div>

                <div class="rounded-2xl border border-white/10 bg-slate-950/60 p-4">
                    <input type="hidden" name="device_approval_required" value="0">
                    <label class="flex items-start gap-3">
                        <input type="checkbox" name="device_approval_required" value="1" @checked($deviceSettings['approval_required']) class="mt-1">
                        <span>
                            <span class="block font-semibold">Require device approval</span>
                            <span class="mt-1 block text-xs leading-5 text-slate-400">New devices wait for an administrator before receiving a mobile token.</span>
                        </span>
                    </label>
                </div>

                <div class="rounded-2xl border border-white/10 bg-slate-950/60 p-4">
                    <input type="hidden" name="secondary_device_enabled" value="0">
                    <label class="flex items-start gap-3">
                        <input type="checkbox" name="secondary_device_enabled" value="1" @checked($deviceSettings['secondary_device_enabled']) class="mt-1">
                        <span>
                            <span class="block font-semibold">Allow secondary devices</span>
                            <span class="mt-1 block text-xs leading-5 text-slate-400">Permit more than one active device for the same salesman/user.</span>
                        </span>
                    </label>
                    <label class="mt-4 block text-xs text-slate-400">
                        Maximum active devices
                        <select name="max_active_devices" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 p-2.5 text-sm text-slate-200">
                            <option value="2" @selected($deviceSettings['max_active_devices'] === 2)>2</option>
                            <option value="3" @selected($deviceSettings['max_active_devices'] === 3)>3</option>
                        </select>
                    </label>
                </div>

                <div class="rounded-2xl border border-white/10 bg-slate-950/60 p-4">
                    <input type="hidden" name="lost_device_workflow_enabled" value="0">
                    <label class="flex items-start gap-3">
                        <input type="checkbox" name="lost_device_workflow_enabled" value="1" @checked($deviceSettings['lost_device_workflow_enabled']) class="mt-1">
                        <span>
                            <span class="block font-semibold">Lost/stolen workflow</span>
                            <span class="mt-1 block text-xs leading-5 text-slate-400">Show dedicated lost/stolen actions that immediately invalidate the device.</span>
                        </span>
                    </label>
                </div>

                <div class="rounded-2xl border border-white/10 bg-slate-950/60 p-4">
                    <input type="hidden" name="device_activity_history_enabled" value="0">
                    <label class="flex items-start gap-3">
                        <input type="checkbox" name="device_activity_history_enabled" value="1" @checked($deviceSettings['activity_history_enabled']) class="mt-1">
                        <span>
                            <span class="block font-semibold">Device activity history</span>
                            <span class="mt-1 block text-xs leading-5 text-slate-400">Store device management actions and important health-state changes.</span>
                        </span>
                    </label>
                </div>

                <div class="rounded-2xl border border-white/10 bg-slate-950/60 p-4">
                    <input type="hidden" name="remote_diagnostics_enabled" value="0">
                    <label class="flex items-start gap-3">
                        <input type="checkbox" name="remote_diagnostics_enabled" value="1" @checked($deviceSettings['remote_diagnostics_enabled']) class="mt-1">
                        <span>
                            <span class="block font-semibold">Admin-requested diagnostics</span>
                            <span class="mt-1 block text-xs leading-5 text-slate-400">Allow an administrator to ask the mobile app for a fresh device-health report.</span>
                        </span>
                    </label>
                </div>

                <div class="rounded-2xl border border-white/10 bg-slate-950/60 p-4">
                    <input type="hidden" name="push_test_enabled" value="0">
                    <label class="flex items-start gap-3">
                        <input type="checkbox" name="push_test_enabled" value="1" @checked($deviceSettings['push_test_enabled']) class="mt-1">
                        <span>
                            <span class="block font-semibold">Push notification test</span>
                            <span class="mt-1 block text-xs leading-5 text-slate-400">Allow a one-off test notification to a selected registered device.</span>
                        </span>
                    </label>
                </div>

                <div class="rounded-2xl border border-white/10 bg-slate-950/60 p-4">
                    <input type="hidden" name="gps_background_test_enabled" value="0">
                    <label class="flex items-start gap-3">
                        <input type="checkbox" name="gps_background_test_enabled" value="1" @checked($deviceSettings['gps_background_test_enabled']) class="mt-1">
                        <span>
                            <span class="block font-semibold">GPS/background test</span>
                            <span class="mt-1 block text-xs leading-5 text-slate-400">Evaluate the latest GPS, permission and background-tracking health snapshot.</span>
                        </span>
                    </label>
                </div>

                <div class="rounded-2xl border border-white/10 bg-slate-950/60 p-4">
                    <input type="hidden" name="sync_test_enabled" value="0">
                    <label class="flex items-start gap-3">
                        <input type="checkbox" name="sync_test_enabled" value="1" @checked($deviceSettings['sync_test_enabled']) class="mt-1">
                        <span>
                            <span class="block font-semibold">Sync test</span>
                            <span class="mt-1 block text-xs leading-5 text-slate-400">Evaluate pending, failed, blocked and last-sync state for the device.</span>
                        </span>
                    </label>
                </div>
            </div>
        </section>

        <div class="flex justify-end lg:col-span-2">
            <button class="rounded-xl bg-indigo-500 px-6 py-3 font-semibold hover:bg-indigo-400">Save policy</button>
        </div>
    </form>
</x-layouts.app>
