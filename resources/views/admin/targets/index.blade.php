<x-layouts.app>
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold">Sales targets</h1>
            <p class="mt-1 text-sm text-slate-400">Targets stay auditable while progress is calculated from approved operational records.</p>
        </div>
        @if(auth()->user()->hasPermission('targets:manage'))
            <a href="{{ route('admin.targets.create') }}" class="rounded-xl bg-indigo-500 px-4 py-2.5 font-semibold">New target</a>
        @endif
    </div>

    <form class="mb-5 flex flex-wrap gap-3">
        <select name="salesman" class="rounded-xl border border-white/10 bg-slate-900 px-4 py-3">
            <option value="">All salesmen</option>
            @foreach($salesmen as $salesman)
                <option value="{{ $salesman->uuid }}" @selected($salesmanUuid === $salesman->uuid)>{{ $salesman->full_name }}</option>
            @endforeach
        </select>
        <select name="type" class="rounded-xl border border-white/10 bg-slate-900 px-4 py-3">
            <option value="">All target types</option>
            @foreach(AppModelsSalesTarget::TYPES as $value)
                <option value="{{ $value }}" @selected($type === $value)>{{ str($value)->replace('_', ' ')->title() }}</option>
            @endforeach
        </select>
        <button class="rounded-xl bg-indigo-500 px-4 py-3 font-semibold">Filter</button>
    </form>

    <div class="space-y-4">
        @forelse($targets as $target)
            @php
                $progress = $progressById[$target->id];
                $displayPercent = min(100, max(0, $progress['progress_percent']));
                $isFuture = $target->period_start->toDateString() > now(auth()->user()->tenant->timezone)->toDateString();
            @endphp
            <section class="rounded-2xl border border-white/10 bg-slate-900 p-5">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div class="text-sm text-slate-400">{{ $target->salesman?->full_name ?? 'Salesman' }}</div>
                        <h2 class="mt-1 text-lg font-semibold">{{ str($target->target_type)->replace('_', ' ')->title() }}</h2>
                        <p class="mt-1 text-sm text-slate-400">{{ $target->period_start->format('Y-m-d') }} → {{ $target->period_end->format('Y-m-d') }}</p>
                    </div>
                    <div class="text-right">
                        <div class="text-lg font-semibold">
                            {{ number_format($progress['achieved_value'], in_array($target->target_type, AppModelsSalesTarget::AMOUNT_TYPES, true) ? 2 : 0) }}
                            /
                            {{ number_format($progress['target_value'], in_array($target->target_type, AppModelsSalesTarget::AMOUNT_TYPES, true) ? 2 : 0) }}
                            {{ $target->currency }}
                        </div>
                        <div class="text-sm text-slate-400">{{ number_format($progress['progress_percent'], 1) }}%</div>
                    </div>
                </div>

                <div class="mt-4 h-2 overflow-hidden rounded-full bg-slate-800">
                    <div class="h-full rounded-full bg-indigo-500" style="width: {{ $displayPercent }}%"></div>
                </div>

                <div class="mt-4 flex flex-wrap items-center justify-between gap-3 text-sm">
                    <span class="text-slate-400">Remaining: {{ number_format($progress['remaining_value'], in_array($target->target_type, AppModelsSalesTarget::AMOUNT_TYPES, true) ? 2 : 0) }} {{ $target->currency }}</span>
                    @if(auth()->user()->hasPermission('targets:manage') && $isFuture)
                        <div class="flex gap-2">
                            <a href="{{ route('admin.targets.edit', $target) }}" class="rounded-lg bg-white/10 px-3 py-2">Edit</a>
                            <form method="POST" action="{{ route('admin.targets.destroy', $target) }}" onsubmit="return confirm('Delete this future target?')">
                                @csrf
                                @method('DELETE')
                                <button class="rounded-lg bg-red-500/15 px-3 py-2 text-red-300">Delete</button>
                            </form>
                        </div>
                    @elseif(!$isFuture)
                        <span class="rounded-lg bg-white/5 px-3 py-2 text-slate-400">Immutable after start</span>
                    @endif
                </div>
            </section>
        @empty
            <div class="rounded-2xl border border-white/10 bg-slate-900 px-5 py-10 text-center text-slate-400">No targets found.</div>
        @endforelse
    </div>

    <div class="mt-5">{{ $targets->links() }}</div>
</x-layouts.app>
