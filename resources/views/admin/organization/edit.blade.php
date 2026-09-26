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

        <section class="space-y-5 rounded-2xl border border-white/10 bg-slate-900 p-6">
            <div>
                <h2 class="font-semibold">{{ __('Field intelligence') }}</h2>
                <p class="mt-1 text-sm text-slate-400">{{ __('Configure smart route planning and territory intelligence for this organization.') }}</p>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-white/10 bg-slate-950/70 p-4">
                    <input type="hidden" name="smart_routes_enabled" value="0">
                    <input
                        type="checkbox"
                        name="smart_routes_enabled"
                        value="1"
                        @checked((bool) old('smart_routes_enabled', $intelligenceSettings['smart_routes_enabled']))
                        class="mt-1 rounded border-white/20 bg-slate-900 text-indigo-500 focus:ring-indigo-500"
                    >
                    <span>
                        <span class="block text-sm font-semibold text-slate-200">{{ __('Enable smart daily route planning') }}</span>
                        <span class="mt-1 block text-xs leading-5 text-slate-500">{{ __('Prioritizes assigned customers using overdue balances, follow-ups, visit recency and distance while keeping the normal assignment scope.') }}</span>
                    </span>
                </label>

                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-white/10 bg-slate-950/70 p-4">
                    <input type="hidden" name="territory_auto_assign_enabled" value="0">
                    <input
                        type="checkbox"
                        name="territory_auto_assign_enabled"
                        value="1"
                        @checked((bool) old('territory_auto_assign_enabled', $intelligenceSettings['territory_auto_assign_enabled']))
                        class="mt-1 rounded border-white/20 bg-slate-900 text-indigo-500 focus:ring-indigo-500"
                    >
                    <span>
                        <span class="block text-sm font-semibold text-slate-200">{{ __('Auto-detect customer territory from GPS') }}</span>
                        <span class="mt-1 block text-xs leading-5 text-slate-500">{{ __('When a customer has coordinates and no explicit territory, FieldPulse can match the shop to the territory polygon automatically.') }}</span>
                    </span>
                </label>

                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-white/10 bg-slate-950/70 p-4">
                    <input type="hidden" name="territory_heat_map_enabled" value="0">
                    <input
                        type="checkbox"
                        name="territory_heat_map_enabled"
                        value="1"
                        @checked((bool) old('territory_heat_map_enabled', $intelligenceSettings['territory_heat_map_enabled']))
                        class="mt-1 rounded border-white/20 bg-slate-900 text-indigo-500 focus:ring-indigo-500"
                    >
                    <span>
                        <span class="block text-sm font-semibold text-slate-200">{{ __('Enable territory heat maps') }}</span>
                        <span class="mt-1 block text-xs leading-5 text-slate-500">{{ __('Allows managers to compare coverage, visits, customer density, stale accounts, sales and collections geographically.') }}</span>
                    </span>
                </label>

                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-white/10 bg-slate-950/70 p-4">
                    <input type="hidden" name="territory_geometry_audit_enabled" value="0">
                    <input
                        type="checkbox"
                        name="territory_geometry_audit_enabled"
                        value="1"
                        @checked((bool) old('territory_geometry_audit_enabled', $intelligenceSettings['territory_geometry_audit_enabled']))
                        class="mt-1 rounded border-white/20 bg-slate-900 text-indigo-500 focus:ring-indigo-500"
                    >
                    <span>
                        <span class="block text-sm font-semibold text-slate-200">{{ __('Audit customer GPS against territory polygons') }}</span>
                        <span class="mt-1 block text-xs leading-5 text-slate-500">{{ __('Flags mapped customers whose saved coordinates fall outside their assigned territory and identifies customers with no territory.') }}</span>
                    </span>
                </label>
            </div>

            <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                <div>
                    <label class="mb-2 block text-sm text-slate-300">{{ __('Nearby opportunity radius') }}</label>
                    <div class="relative">
                        <input type="number" step="0.5" min="0.5" max="25" name="route_nearby_radius_km" value="{{ old('route_nearby_radius_km', $intelligenceSettings['route_nearby_radius_km']) }}" class="w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3 pe-12">
                        <span class="pointer-events-none absolute inset-y-0 end-4 flex items-center text-xs text-slate-500">km</span>
                    </div>
                    <p class="mt-1 text-xs text-slate-500">{{ __('Used when suggesting worthwhile nearby customers during the day.') }}</p>
                </div>

                <div>
                    <label class="mb-2 block text-sm text-slate-300">{{ __('Maximum extra opportunities') }}</label>
                    <input type="number" min="0" max="10" name="route_max_opportunities" value="{{ old('route_max_opportunities', $intelligenceSettings['route_max_opportunities']) }}" class="w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3">
                    <p class="mt-1 text-xs text-slate-500">{{ __('Limits optional nearby stops so the assigned route remains the main plan.') }}</p>
                </div>

                <div>
                    <label class="mb-2 block text-sm text-slate-300">{{ __('Planning travel speed') }}</label>
                    <div class="relative">
                        <input type="number" step="1" min="5" max="100" name="route_average_speed_kph" value="{{ old('route_average_speed_kph', $intelligenceSettings['route_average_speed_kph']) }}" class="w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3 pe-14">
                        <span class="pointer-events-none absolute inset-y-0 end-4 flex items-center text-xs text-slate-500">km/h</span>
                    </div>
                    <p class="mt-1 text-xs text-slate-500">{{ __('Used only for route ETA and capacity estimates; GPS distance remains authoritative for actual travel.') }}</p>
                </div>

                <div>
                    <label class="mb-2 block text-sm text-slate-300">{{ __('Daily route time buffer') }}</label>
                    <div class="relative">
                        <input type="number" min="0" max="180" name="route_time_buffer_minutes" value="{{ old('route_time_buffer_minutes', $intelligenceSettings['route_time_buffer_minutes']) }}" class="w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3 pe-14">
                        <span class="pointer-events-none absolute inset-y-0 end-4 flex items-center text-xs text-slate-500">min</span>
                    </div>
                    <p class="mt-1 text-xs text-slate-500">{{ __('Keeps room for traffic, parking, breaks and unexpected delays.') }}</p>
                </div>

                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-white/10 bg-slate-950/70 p-4">
                    <input type="hidden" name="route_enforce_workday_capacity" value="0">
                    <input type="checkbox" name="route_enforce_workday_capacity" value="1" @checked((bool) old('route_enforce_workday_capacity', $intelligenceSettings['route_enforce_workday_capacity'])) class="mt-1 rounded border-white/20 bg-slate-900 text-indigo-500 focus:ring-indigo-500">
                    <span>
                        <span class="block text-sm font-semibold text-slate-200">{{ __('Respect workday route capacity') }}</span>
                        <span class="mt-1 block text-xs leading-5 text-slate-500">{{ __('Estimate whether remaining travel and visit time fit inside the Attendance & GPS workday window and flag overflow stops.') }}</span>
                    </span>
                </label>

                <div>
                    <label class="mb-2 block text-sm text-slate-300">{{ __('Under-covered territory threshold') }}</label>
                    <div class="relative">
                        <input type="number" min="1" max="100" name="territory_under_covered_threshold_percent" value="{{ old('territory_under_covered_threshold_percent', $intelligenceSettings['territory_under_covered_threshold_percent']) }}" class="w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3 pe-10">
                        <span class="pointer-events-none absolute inset-y-0 end-4 flex items-center text-xs text-slate-500">%</span>
                    </div>
                    <p class="mt-1 text-xs text-slate-500">{{ __('Territories below this visit coverage are highlighted for management attention.') }}</p>
                </div>

                <div>
                    <label class="mb-2 block text-sm text-slate-300">{{ __('Stale customer age') }}</label>
                    <div class="relative">
                        <input type="number" min="7" max="180" name="territory_stale_customer_days" value="{{ old('territory_stale_customer_days', $intelligenceSettings['territory_stale_customer_days']) }}" class="w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3 pe-14">
                        <span class="pointer-events-none absolute inset-y-0 end-4 flex items-center text-xs text-slate-500">days</span>
                    </div>
                    <p class="mt-1 text-xs text-slate-500">{{ __('A customer is treated as stale when no completed visit exists inside this number of days.') }}</p>
                </div>

                <div>
                    <label class="mb-2 block text-sm text-slate-300">{{ __('Stale-account attention threshold') }}</label>
                    <div class="relative">
                        <input type="number" min="1" max="100" name="territory_stale_attention_percent" value="{{ old('territory_stale_attention_percent', $intelligenceSettings['territory_stale_attention_percent']) }}" class="w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3 pe-10">
                        <span class="pointer-events-none absolute inset-y-0 end-4 flex items-center text-xs text-slate-500">%</span>
                    </div>
                    <p class="mt-1 text-xs text-slate-500">{{ __('Highlights territories when the stale-customer share reaches or exceeds this level.') }}</p>
                </div>
            </div>
        </section>

        <section class="space-y-5 rounded-2xl border border-white/10 bg-slate-900 p-6">
            <div>
                <h2 class="font-semibold">{{ __('Gamification') }}</h2>
                <p class="mt-1 text-sm text-slate-400">{{ __('Control whether verified FieldPulse business events may participate in future points, achievements, streaks and challenges.') }}</p>
            </div>

            <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-white/10 bg-slate-950/70 p-4">
                <input type="hidden" name="gamification_enabled" value="0">
                <input
                    type="checkbox"
                    name="gamification_enabled"
                    value="1"
                    @checked((bool) old('gamification_enabled', $intelligenceSettings['gamification_enabled']))
                    class="mt-1 rounded border-white/20 bg-slate-900 text-indigo-500 focus:ring-indigo-500"
                >
                <span>
                    <span class="block text-sm font-semibold text-slate-200">{{ __('Enable gamification for this organization') }}</span>
                    <span class="mt-1 block text-xs leading-5 text-slate-500">{{ __('Disabled by default. When off, FieldPulse does not award points, badges, streaks, challenges or leaderboard progress. Future rewards will only use verified business events such as completed visits, confirmed collections, approved orders and achieved targets.') }}</span>
                </span>
            </label>

            <div class="rounded-xl border border-indigo-400/10 bg-indigo-500/5 px-4 py-3 text-xs leading-5 text-slate-400">
                {{ __('This switch is the organization-level gate for all future gamification features. Individual gamification options will remain subordinate to this setting.') }}
            </div>
        </section>

        <section class="space-y-5 rounded-2xl border border-white/10 bg-slate-900 p-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="font-semibold">{{ __('BusinessOS integration') }}</h2>
                    <p class="mt-1 text-sm text-slate-400">{{ __('Keep FieldPulse standalone while allowing controlled master-data and transaction synchronization with BusinessOS.') }}</p>
                </div>
                <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $businessOsSettings['platform_available'] ? 'bg-emerald-500/10 text-emerald-300' : 'bg-amber-500/10 text-amber-300' }}">
                    {{ $businessOsSettings['platform_available'] ? __('Connector configured') : __('Connector not configured on server') }}
                </span>
            </div>

            <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-white/10 bg-slate-950/70 p-4">
                <input type="hidden" name="businessos_enabled" value="0">
                <input
                    type="checkbox"
                    name="businessos_enabled"
                    value="1"
                    @checked((bool) old('businessos_enabled', $businessOsSettings['requested_enabled']))
                    class="mt-1 rounded border-white/20 bg-slate-900 text-indigo-500 focus:ring-indigo-500"
                >
                <span>
                    <span class="block text-sm font-semibold text-slate-200">{{ __('Enable BusinessOS synchronization for this organization') }}</span>
                    <span class="mt-1 block text-xs leading-5 text-slate-500">{{ __('The switch becomes operational only when the platform connector URL and token are configured on the server.') }}</span>
                </span>
            </label>

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label class="mb-2 block text-sm text-slate-300">{{ __('BusinessOS organization key') }}</label>
                    <input
                        name="businessos_organization_key"
                        value="{{ old('businessos_organization_key', $businessOsSettings['organization_key']) }}"
                        maxlength="120"
                        class="w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3"
                        placeholder="{{ __('Example: acme-distribution') }}"
                    >
                    <p class="mt-1 text-xs text-slate-500">{{ __('Maps this FieldPulse tenant to the corresponding organization in BusinessOS. This is not an API secret.') }}</p>
                </div>

                <div>
                    <label class="mb-2 block text-sm text-slate-300">{{ __('Automatic sync interval') }}</label>
                    <select name="businessos_sync_interval_minutes" class="w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-3">
                        @foreach([
                            5 => __('Every 5 minutes'),
                            15 => __('Every 15 minutes'),
                            30 => __('Every 30 minutes'),
                            60 => __('Hourly'),
                            120 => __('Every 2 hours'),
                            240 => __('Every 4 hours'),
                        ] as $minutes => $label)
                            <option value="{{ $minutes }}" @selected((int) old('businessos_sync_interval_minutes', $businessOsSettings['sync_interval_minutes']) === $minutes)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <p class="mb-3 text-sm font-semibold text-slate-300">{{ __('Allowed synchronization directions') }}</p>
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    @foreach([
                        'pull_products' => __('Pull products from BusinessOS'),
                        'pull_customers' => __('Pull customers from BusinessOS'),
                        'pull_prices' => __('Pull prices from BusinessOS'),
                        'push_orders' => __('Push approved orders to BusinessOS'),
                        'push_collections' => __('Push verified collections to BusinessOS'),
                        'push_field_customers' => __('Push field-created customers to BusinessOS'),
                    ] as $key => $label)
                        <label class="flex items-center gap-3 rounded-xl border border-white/10 bg-slate-950/60 px-4 py-3 text-sm">
                            <input type="hidden" name="businessos_{{ $key }}" value="0">
                            <input
                                type="checkbox"
                                name="businessos_{{ $key }}"
                                value="1"
                                @checked((bool) old('businessos_'.$key, $businessOsSettings[$key]))
                                class="rounded border-white/20 bg-slate-900 text-indigo-500 focus:ring-indigo-500"
                            >
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="rounded-xl border border-cyan-400/10 bg-cyan-500/5 px-4 py-3 text-xs leading-5 text-slate-400">
                {{ __('BusinessOS credentials and connector endpoints are platform-managed and are never displayed here. FieldPulse remains independently usable when the integration is disabled or unavailable.') }}
                @if($businessOsSettings['base_url'])
                    <span class="mt-1 block">{{ __('Configured connector host:') }} <span class="font-mono text-slate-300">{{ $businessOsSettings['base_url'] }}</span></span>
                @endif
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
