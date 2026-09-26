<x-layouts.app>
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold">Mobile diagnostics</h1>
            <p class="mt-1 text-sm text-slate-400">Sanitized mobile failures and recovery events reported by registered devices.</p>
        </div>
        <a href="{{ route('admin.devices.index') }}" class="rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm font-semibold hover:bg-white/10">Registered devices</a>
    </div>

    <form method="get" class="mb-5 grid gap-3 rounded-2xl border border-white/10 bg-slate-900 p-4 md:grid-cols-4">
        <label class="block">
            <span class="text-xs font-semibold uppercase tracking-wide text-slate-400">Severity</span>
            <select name="severity" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5">
                <option value="">All</option>
                @foreach(['info','warning','error','critical'] as $severity)
                    <option value="{{ $severity }}" @selected(($filters['severity'] ?? '') === $severity)>{{ str($severity)->title() }}</option>
                @endforeach
            </select>
        </label>
        <label class="block">
            <span class="text-xs font-semibold uppercase tracking-wide text-slate-400">Area</span>
            <select name="area" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5">
                <option value="">All</option>
                @foreach($areas as $area)
                    <option value="{{ $area }}" @selected(($filters['area'] ?? '') === $area)>{{ $area }}</option>
                @endforeach
            </select>
        </label>
        <label class="block">
            <span class="text-xs font-semibold uppercase tracking-wide text-slate-400">User</span>
            <input name="user" value="{{ $filters['user'] ?? '' }}" placeholder="Name or email" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5">
        </label>
        <div class="flex items-end gap-2">
            <button class="rounded-xl bg-indigo-500 px-4 py-2.5 font-semibold">Filter</button>
            <a href="{{ route('admin.mobile-diagnostics.index') }}" class="rounded-xl border border-white/10 bg-white/5 px-4 py-2.5">Reset</a>
        </div>
    </form>

    <div class="overflow-hidden rounded-2xl border border-white/10 bg-slate-900">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-white/5 text-slate-300">
                    <tr>
                        <th class="px-5 py-3">When</th>
                        <th class="px-5 py-3">Severity</th>
                        <th class="px-5 py-3">Area</th>
                        <th class="px-5 py-3">User / device</th>
                        <th class="px-5 py-3">Message</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/10">
                @forelse($diagnostics as $diagnostic)
                    <tr class="align-top">
                        <td class="whitespace-nowrap px-5 py-4">
                            <div>{{ $diagnostic->occurred_at?->format('Y-m-d H:i') }}</div>
                            <div class="text-xs text-slate-500">{{ $diagnostic->occurred_at?->diffForHumans() }}</div>
                        </td>
                        <td class="px-5 py-4">
                            <span class="rounded-full border border-white/10 bg-white/5 px-2.5 py-1 text-xs font-semibold">{{ str($diagnostic->severity)->upper() }}</span>
                        </td>
                        <td class="px-5 py-4">
                            <div class="font-medium">{{ $diagnostic->area }}</div>
                            @if($diagnostic->code)<div class="mt-1 text-xs text-slate-500">{{ $diagnostic->code }}</div>@endif
                        </td>
                        <td class="px-5 py-4">
                            <div>{{ $diagnostic->user?->name ?? 'Unknown user' }}</div>
                            <div class="text-xs text-slate-500">{{ $diagnostic->device?->device_model ?? 'Unknown device' }} · {{ $diagnostic->device?->app_version ?? '—' }}</div>
                        </td>
                        <td class="max-w-xl px-5 py-4">
                            <p class="break-words">{{ $diagnostic->message }}</p>
                            @if($diagnostic->context)
                                <div class="mt-2 flex flex-wrap gap-1.5">
                                    @foreach($diagnostic->context as $key => $value)
                                        <span class="rounded-lg bg-slate-950 px-2 py-1 text-[11px] text-slate-400">{{ $key }}={{ is_scalar($value) ? $value : json_encode($value) }}</span>
                                    @endforeach
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-12 text-center text-slate-400">No mobile diagnostics have been reported.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-5">{{ $diagnostics->links() }}</div>
</x-layouts.app>
