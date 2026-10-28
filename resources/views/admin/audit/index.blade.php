<x-layouts.app>
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[.2em] text-indigo-400">Security & accountability</p>
            <h1 class="mt-2 text-3xl font-bold">Audit logs</h1>
            <p class="mt-2 text-sm text-slate-400">Review who changed what and when. Entries are limited to your organization.</p>
        </div>
        <a href="{{ route('admin.audit.export', request()->only(['search', 'event', 'from', 'to'])) }}" class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-indigo-900/20 hover:bg-indigo-500">
            <span aria-hidden="true">↓</span> Export CSV
        </a>
    </div>

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <div class="fp-surface p-5"><p class="text-sm text-slate-400">Matching events</p><p class="mt-2 text-3xl font-bold">{{ number_format($totalLogs) }}</p></div>
        <div class="fp-surface p-5"><p class="text-sm text-slate-400">Last 24 hours (filtered)</p><p class="mt-2 text-3xl font-bold text-indigo-400">{{ number_format($recentLogs) }}</p></div>
        <div class="fp-surface p-5"><p class="text-sm text-slate-400">Current page</p><p class="mt-2 text-3xl font-bold">{{ number_format($logs->count()) }}</p></div>
    </div>

    <form method="GET" action="{{ route('admin.audit.index') }}" class="fp-surface mb-6 grid gap-4 p-5 md:grid-cols-2 xl:grid-cols-5">
        <label class="block"><span class="mb-2 block text-xs font-semibold text-slate-400">Search</span><input type="search" name="search" value="{{ request('search') }}" placeholder="Actor, event, IP, subject" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5 text-sm text-slate-100"></label>
        <label class="block"><span class="mb-2 block text-xs font-semibold text-slate-400">Event</span><select name="event" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5 text-sm text-slate-100"><option value="">All events</option>@foreach($events as $event)<option value="{{ $event }}" @selected(request('event') === $event)>{{ str($event)->replace(['_', '.'], ' ')->title() }}</option>@endforeach</select></label>
        <label class="block"><span class="mb-2 block text-xs font-semibold text-slate-400">From</span><input type="date" name="from" value="{{ request('from') }}" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5 text-sm text-slate-100"></label>
        <label class="block"><span class="mb-2 block text-xs font-semibold text-slate-400">To</span><input type="date" name="to" value="{{ request('to') }}" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2.5 text-sm text-slate-100"></label>
        <div class="flex items-end gap-2"><button class="rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500" type="submit">Apply</button><a class="rounded-xl border border-white/10 px-4 py-2.5 text-sm text-slate-400" href="{{ route('admin.audit.index') }}">Reset</a></div>
    </form>

    <div class="fp-surface overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/10 px-5 py-4"><h2 class="font-semibold">Activity history</h2><span class="text-xs text-slate-400">{{ $logs->firstItem() ?? 0 }}–{{ $logs->lastItem() ?? 0 }} of {{ number_format($logs->total()) }}</span></div>
        <div class="divide-y divide-white/10">
            @forelse($logs as $log)
                @php
                    $event = strtolower($log->event ?? '');
                    $tone = str_contains($event, 'delet') || str_contains($event, 'remov') ? 'rose' : (str_contains($event, 'creat') || str_contains($event, 'approv') ? 'emerald' : 'indigo');
                    $initial = strtoupper(substr($log->actor?->name ?? 'S', 0, 1));
                @endphp
                <article class="px-5 py-4 transition hover:bg-white/[.025]">
                    <div class="flex flex-wrap items-start gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-500/15 font-bold text-indigo-300">{{ $initial }}</div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-semibold">{{ $log->actor?->name ?? 'System' }}</span>
                                <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $tone === 'rose' ? 'bg-rose-500/15 text-rose-300' : ($tone === 'emerald' ? 'bg-emerald-500/15 text-emerald-300' : 'bg-indigo-500/15 text-indigo-300') }}">{{ str($log->event)->replace(['_', '.'], ' ')->title() }}</span>
                            </div>
                            <p class="mt-1 break-words text-sm text-slate-400">{{ class_basename($log->subject_type ?? 'Record') }} @if($log->subject_id) <span class="font-mono text-xs">#{{ $log->subject_id }}</span> @endif</p>
                            <p class="mt-2 text-xs text-slate-500">{{ $log->created_at?->format('d M Y, H:i:s') }} @if($log->ip_address) · IP {{ $log->ip_address }} @endif</p>
                            @if($log->old_values || $log->new_values)
                                <details class="mt-3 rounded-xl border border-white/10 bg-slate-950/40 p-3">
                                    <summary class="cursor-pointer text-sm font-medium text-indigo-300">View changes</summary>
                                    <div class="mt-3 grid gap-3 lg:grid-cols-2">
                                        <div class="min-w-0 rounded-lg bg-slate-950 p-3"><p class="mb-2 text-xs font-semibold uppercase text-slate-400">Before</p><pre class="overflow-x-auto whitespace-pre-wrap break-all text-xs text-slate-300">{{ json_encode($log->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '—' }}</pre></div>
                                        <div class="min-w-0 rounded-lg bg-slate-950 p-3"><p class="mb-2 text-xs font-semibold uppercase text-slate-400">After</p><pre class="overflow-x-auto whitespace-pre-wrap break-all text-xs text-slate-300">{{ json_encode($log->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '—' }}</pre></div>
                                    </div>
                                </details>
                            @endif
                        </div>
                    </div>
                </article>
            @empty
                <div class="px-5 py-16 text-center"><p class="text-lg font-semibold">No matching audit events</p><p class="mt-2 text-sm text-slate-400">Try adjusting the search or date filters.</p></div>
            @endforelse
        </div>
    </div>
    <div class="mt-5">{{ $logs->links() }}</div>
</x-layouts.app>
