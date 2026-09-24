<x-layouts.app>
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold">Organization</h1>
            <p class="mt-1 text-sm text-slate-400">Company profile, operational timezone and usage at a glance.</p>
        </div>
        <div class="rounded-xl border border-white/10 bg-slate-900 px-4 py-3 text-sm text-slate-300">
            Status:
            <span class="font-semibold {{ $tenant->subscription_status === 'active' ? 'text-emerald-300' : 'text-amber-300' }}">{{ str($tenant->subscription_status)->title() }}</span>
        </div>
    </div>

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach([
            'Users' => $stats['users'],
            'Branches' => $stats['branches'],
            'Salesmen' => $stats['salesmen'],
            'Customers' => $stats['customers'],
            'Orders' => $stats['orders'],
            'Visits' => $stats['visits'],
        ] as $label => $value)
            <div class="rounded-2xl border border-white/10 bg-slate-900 p-5">
                <div class="text-sm text-slate-400">{{ $label }}</div>
                <div class="mt-2 text-2xl font-bold">{{ number_format($value) }}</div>
            </div>
        @endforeach
    </section>

    <form method="POST" action="{{ route('organization.update') }}" class="mt-6 max-w-3xl space-y-6">
        @csrf
        @method('PUT')

        <section class="space-y-5 rounded-2xl border border-white/10 bg-slate-900 p-6">
            <h2 class="font-semibold">Company profile</h2>

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label class="mb-2 block text-sm text-slate-300">Company name</label>
                    <input name="name" value="{{ old('name', $tenant->name) }}" class="w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3" required>
                </div>
                <div>
                    <label class="mb-2 block text-sm text-slate-300">Company identifier (slug)</label>
                    <input value="{{ $tenant->slug }}" class="w-full cursor-not-allowed rounded-xl border border-white/10 bg-slate-950/60 px-4 py-3 text-slate-400" disabled>
                    <p class="mt-1 text-xs text-slate-500">Managed by the platform team because it is used as a sign-in identifier.</p>
                </div>
            </div>

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label class="mb-2 block text-sm text-slate-300">Timezone</label>
                    <select name="timezone" class="w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3" required>
                        @foreach($timezones as $timezone)
                            <option value="{{ $timezone }}" @selected(old('timezone', $tenant->timezone) === $timezone)>{{ $timezone }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-slate-500">Workday windows, reports and live tracking use this timezone.</p>
                </div>
                <div>
                    <label class="mb-2 block text-sm text-slate-300">Contact email</label>
                    <input type="email" name="contact_email" value="{{ old('contact_email', $tenant->contact_email) }}" class="w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3">
                </div>
            </div>

            <div class="flex flex-wrap gap-6 rounded-xl bg-white/5 px-4 py-3 text-sm">
                <div>
                    <span class="text-slate-400">Organization UUID:</span>
                    <span class="font-mono text-xs text-slate-300">{{ $tenant->uuid }}</span>
                </div>
                <div>
                    <span class="text-slate-400">Created:</span>
                    <span class="text-slate-300">{{ $tenant->created_at?->format('Y-m-d') }}</span>
                </div>
            </div>
        </section>

        <section class="space-y-5 rounded-2xl border border-white/10 bg-slate-900 p-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="font-semibold">{{ __('Ask FieldPulse AI policy') }}</h2>
                    <p class="mt-1 text-sm text-slate-400">{{ __('Control external AI access and conversation retention for this organization.') }}</p>
                </div>
                <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $aiPolicy['external_available'] ? 'bg-emerald-500/10 text-emerald-300' : 'bg-amber-500/10 text-amber-300' }}">
                    {{ $aiPolicy['external_available'] ? __('Platform AI available') : __('Platform AI disabled') }}
                </span>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-white/10 bg-slate-950/70 p-4">
                    <input type="hidden" name="ai_enabled" value="0">
                    <input
                        type="checkbox"
                        name="ai_enabled"
                        value="1"
                        @checked((bool) old('ai_enabled', data_get($tenant->settings, 'ai.enabled', true)))
                        class="mt-1 rounded border-white/20 bg-slate-900 text-indigo-500 focus:ring-indigo-500"
                    >
                    <span>
                        <span class="block text-sm font-semibold text-slate-200">{{ __('Enable external AI for this organization') }}</span>
                        <span class="mt-1 block text-xs leading-5 text-slate-500">{{ __('When disabled, Ask FieldPulse remains available in grounded local mode and does not call the external LLM.') }}</span>
                    </span>
                </label>

                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-white/10 bg-slate-950/70 p-4">
                    <input type="hidden" name="ai_allow_customer_data" value="0">
                    <input
                        type="checkbox"
                        name="ai_allow_customer_data"
                        value="1"
                        @checked((bool) old('ai_allow_customer_data', data_get($tenant->settings, 'ai.allow_customer_data', true)))
                        @disabled(! $aiPolicy['customer_data_available'])
                        class="mt-1 rounded border-white/20 bg-slate-900 text-indigo-500 focus:ring-indigo-500 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                    <span>
                        <span class="block text-sm font-semibold text-slate-200">{{ __('Allow customer-level AI tools') }}</span>
                        <span class="mt-1 block text-xs leading-5 text-slate-500">
                            {{ $aiPolicy['customer_data_available']
                                ? __('Authorized users may ask about customer balances, aging, orders, follow-ups, and other customer-specific records.')
                                : __('The platform administrator has disabled customer-level AI data globally.') }}
                        </span>
                    </span>
                </label>
            </div>

            <div class="max-w-md">
                <label class="mb-2 block text-sm text-slate-300">{{ __('Conversation history retention') }}</label>
                <select name="ai_history_retention_days" class="w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3">
                    @foreach([
                        30 => __('30 days'),
                        60 => __('60 days'),
                        90 => __('90 days'),
                        180 => __('180 days'),
                        365 => __('1 year'),
                        0 => __('Keep indefinitely'),
                    ] as $days => $label)
                        <option value="{{ $days }}" @selected((int) old('ai_history_retention_days', $aiPolicy['history_retention_days']) === $days)>{{ $label }}</option>
                    @endforeach
                </select>
                <p class="mt-2 text-xs leading-5 text-slate-500">{{ __('Expired Ask FieldPulse conversations are removed with their messages. Tool access remains read-only and permission-aware.') }}</p>
            </div>

            <div class="rounded-xl border border-cyan-400/10 bg-cyan-500/5 px-4 py-3 text-xs leading-5 text-slate-400">
                {{ __('Provider credentials remain platform-managed. Organization settings can restrict AI access but cannot expose or override the server API key.') }}
            </div>
        </section>

        <div class="flex flex-wrap gap-3">
            @if(auth()->user()->hasPermission('settings:manage'))
                <button class="rounded-xl bg-indigo-500 px-5 py-3 font-semibold hover:bg-indigo-400">Save profile</button>
            @endif
            @if(auth()->user()->hasPermission('settings:view'))
                <a href="{{ route('tracking.edit') }}" class="rounded-xl bg-white/10 px-5 py-3 hover:bg-white/20">Tracking policy</a>
            @endif
        </div>
    </form>
</x-layouts.app>
