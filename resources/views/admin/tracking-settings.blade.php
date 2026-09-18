<x-layouts.app>
<div class="space-y-6">
    <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
        <div>
            <p class="font-semibold text-indigo-600 dark:text-indigo-400">{{ $tenant->name }}</p>
            <h1 class="mt-1 text-3xl font-black">Attendance & tracking settings</h1>
            <p class="mt-2 text-sm text-slate-500">Tenant timezone: <span class="font-semibold text-slate-700 dark:text-slate-200">{{ $settings['timezone'] }}</span></p>
        </div>
        <div class="rounded-2xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-700 dark:text-emerald-300">Privacy-first · work-session scoped</div>
    </div>

    @if(session('status'))
        <div class="rounded-xl border border-emerald-500/20 bg-emerald-500/10 p-4 text-emerald-700 dark:text-emerald-300">{{ session('status') }}</div>
    @endif

    @if($errors->any())
        <div class="rounded-xl border border-rose-500/20 bg-rose-500/10 p-4 text-sm text-rose-700 dark:text-rose-300">
            <ul class="list-disc space-y-1 pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('tracking.update') }}" class="grid gap-6 xl:grid-cols-2">
        @csrf @method('PUT')

        <section class="space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-slate-900">
            <div><h2 class="text-xl font-bold">Work session</h2><p class="mt-1 text-sm text-slate-500">Choose whether the salesman starts manually or the mobile app starts automatically during the company work window.</p></div>
            <label class="block text-sm font-medium">Start mode
                <select name="work_session_start_mode" class="mt-2 w-full rounded-xl border border-slate-200 bg-transparent p-3 dark:border-white/10">
                    <option value="manual" @selected($settings['work_session_start_mode']==='manual')>Manual — salesman taps Start Day</option>
                    <option value="automatic" @selected($settings['work_session_start_mode']==='automatic')>Automatic — app starts when available inside work hours</option>
                </select>
            </label>
            <div class="grid grid-cols-2 gap-4">
                <label class="text-sm font-medium">Workday start<input type="time" name="workday_start_time" value="{{ $settings['workday_start_time'] }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-transparent p-3 dark:border-white/10"></label>
                <label class="text-sm font-medium">Workday end<input type="time" name="workday_end_time" value="{{ $settings['workday_end_time'] }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-transparent p-3 dark:border-white/10"></label>
            </div>
            <p class="text-xs text-slate-500">Overnight windows are supported, for example 20:00 → 04:00.</p>
            <input type="hidden" name="auto_end_session" value="0">
            <label class="flex items-start gap-3 rounded-xl bg-slate-50 p-4 dark:bg-white/5"><input class="mt-1" type="checkbox" name="auto_end_session" value="1" @checked($settings['auto_end_session'])><span><strong class="block">Auto-end work session</strong><span class="text-sm text-slate-500">Mobile calls the normal End Day endpoint when the work window closes.</span></span></label>
            <label class="block text-sm font-medium">Privacy policy version<input name="privacy_policy_version" value="{{ $settings['privacy_policy_version'] }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-transparent p-3 dark:border-white/10"></label>
        </section>

        <section class="space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-slate-900">
            <div><h2 class="text-xl font-bold">GPS tracking</h2><p class="mt-1 text-sm text-slate-500">Attendance can remain active even if continuous GPS tracking is disabled.</p></div>
            <input type="hidden" name="gps_tracking_enabled" value="0">
            <label class="flex items-start gap-3 rounded-xl bg-slate-50 p-4 dark:bg-white/5"><input class="mt-1" type="checkbox" name="gps_tracking_enabled" value="1" @checked($settings['gps_tracking_enabled'])><span><strong class="block">Enable continuous GPS</strong><span class="text-sm text-slate-500">Collect only while an allowed active work session exists.</span></span></label>
            <div class="grid grid-cols-2 gap-4">
                <label class="text-sm font-medium">Moving interval<input type="number" name="gps_moving_interval_seconds" value="{{ $settings['gps_moving_interval_seconds'] }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-transparent p-3 dark:border-white/10"><span class="text-xs text-slate-500">seconds</span></label>
                <label class="text-sm font-medium">Stationary interval<input type="number" name="gps_stationary_interval_seconds" value="{{ $settings['gps_stationary_interval_seconds'] }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-transparent p-3 dark:border-white/10"><span class="text-xs text-slate-500">seconds</span></label>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <label class="text-sm font-medium">GPS stale after<input type="number" name="gps_stale_after_minutes" value="{{ $settings['gps_stale_after_minutes'] }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-transparent p-3 dark:border-white/10"><span class="text-xs text-slate-500">minutes</span></label>
                <label class="text-sm font-medium">GPS retention<input type="number" name="gps_retention_days" value="{{ $settings['gps_retention_days'] }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-transparent p-3 dark:border-white/10"><span class="text-xs text-slate-500">days</span></label>
            </div>
        </section>

        <section class="space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-slate-900 xl:col-span-2">
            <div><h2 class="text-xl font-bold">Tracking reminders</h2><p class="mt-1 text-sm text-slate-500">Supervisors can remind offline staff manually. Optional automatic reminders use the same cooldown and daily limits.</p></div>
            <input type="hidden" name="tracking_auto_reminder_enabled" value="0">
            <label class="flex items-start gap-3 rounded-xl bg-slate-50 p-4 dark:bg-white/5"><input class="mt-1" type="checkbox" name="tracking_auto_reminder_enabled" value="1" @checked($settings['tracking_auto_reminder_enabled'])><span><strong class="block">Automatic tracking reminders</strong><span class="text-sm text-slate-500">Send after a scheduled employee has not started or an active session has stale GPS.</span></span></label>
            <div class="grid gap-4 sm:grid-cols-3">
                <label class="text-sm font-medium">Initial delay<input type="number" name="tracking_reminder_delay_minutes" value="{{ $settings['tracking_reminder_delay_minutes'] }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-transparent p-3 dark:border-white/10"><span class="text-xs text-slate-500">minutes</span></label>
                <label class="text-sm font-medium">Repeat interval<input type="number" name="tracking_reminder_repeat_minutes" value="{{ $settings['tracking_reminder_repeat_minutes'] }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-transparent p-3 dark:border-white/10"><span class="text-xs text-slate-500">minutes</span></label>
                <label class="text-sm font-medium">Daily limit<input type="number" name="tracking_reminder_daily_limit" value="{{ $settings['tracking_reminder_daily_limit'] }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-transparent p-3 dark:border-white/10"><span class="text-xs text-slate-500">per salesman</span></label>
            </div>
            <p class="text-xs text-slate-500">Automatic mode cannot force-start a phone that Android has force-stopped. The reminder asks the salesman to open Field Sales so the app can restore the work session and foreground tracking service.</p>
        </section>

        <div class="flex justify-end xl:col-span-2"><button class="rounded-xl bg-indigo-600 px-6 py-3 font-semibold text-white shadow-soft hover:bg-indigo-500">Save policy</button></div>
    </form>
</div>
</x-layouts.app>
