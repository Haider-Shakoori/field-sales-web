<x-layouts.app>
    <div class="mb-6">
        <h1 class="text-2xl font-bold">Audit log</h1>
        <p class="mt-1 text-sm text-slate-400">Tenant-scoped mutation history. Passwords and secrets are never recorded here.</p>
    </div>

    <div class="space-y-3">
        @forelse($logs as $log)
            <article class="rounded-2xl border border-white/10 bg-slate-900 p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <span class="font-semibold">{{ $log->event }}</span>
                        <span class="ml-2 text-sm text-slate-400">{{ $log->subject_type }} #{{ $log->subject_id }}</span>
                    </div>
                    <div class="text-sm text-slate-400">{{ $log->created_at->format('Y-m-d H:i:s') }}</div>
                </div>
                <div class="mt-2 text-sm text-slate-400">
                    Actor: {{ $log->actor?->name ?? 'System' }}
                    @if($log->ip_address) · IP {{ $log->ip_address }} @endif
                </div>
                @if($log->old_values || $log->new_values)
                    <div class="mt-4 grid gap-3 lg:grid-cols-2">
                        <div class="rounded-xl bg-slate-950 p-3">
                            <p class="mb-2 text-xs font-semibold uppercase text-slate-500">Before</p>
                            <pre class="overflow-x-auto whitespace-pre-wrap text-xs text-slate-300">{{ json_encode($log->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                        </div>
                        <div class="rounded-xl bg-slate-950 p-3">
                            <p class="mb-2 text-xs font-semibold uppercase text-slate-500">After</p>
                            <pre class="overflow-x-auto whitespace-pre-wrap text-xs text-slate-300">{{ json_encode($log->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                        </div>
                    </div>
                @endif
            </article>
        @empty
            <div class="rounded-2xl border border-white/10 bg-slate-900 p-10 text-center text-slate-400">No audit events yet.</div>
        @endforelse
    </div>

    <div class="mt-5">{{ $logs->links() }}</div>
</x-layouts.app>
