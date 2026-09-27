<x-layouts.app>
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold">{{ $assignment->salesman?->full_name }}</h1>
            <p class="mt-1 text-sm text-slate-400">Assignment {{ $assignment->uuid }}</p>
        </div>
        @if(auth()->user()->hasPermission('sales-team:manage'))
            <a href="{{ route('admin.salesman-assignments.edit', $assignment) }}" class="rounded-xl bg-indigo-500 px-4 py-2.5 font-semibold">Edit</a>
        @endif
    </div>

    <section class="rounded-2xl border border-white/10 bg-slate-900 p-5">
        <dl class="grid gap-4 sm:grid-cols-2">
            <div><dt class="text-sm text-slate-400">Salesman</dt><dd class="mt-1">{{ $assignment->salesman?->employee_code }} — {{ $assignment->salesman?->full_name }}</dd></div>
            <div><dt class="text-sm text-slate-400">Branch</dt><dd class="mt-1">{{ $assignment->branch?->name ?? 'Unassigned' }}</dd></div>
            <div><dt class="text-sm text-slate-400">Territory</dt><dd class="mt-1">{{ $assignment->territory?->name ?? 'Unassigned' }}</dd></div>
            <div><dt class="text-sm text-slate-400">Route</dt><dd class="mt-1">{{ $assignment->route?->name ?? 'Unassigned' }}</dd></div>
            <div><dt class="text-sm text-slate-400">Supervisor</dt><dd class="mt-1">{{ $assignment->supervisor?->full_name ?? 'Unassigned' }}</dd></div>
            <div><dt class="text-sm text-slate-400">Status</dt><dd class="mt-1">{{ $assignment->isCurrent() ? 'Current' : 'Historical' }}</dd></div>
            <div><dt class="text-sm text-slate-400">Effective from</dt><dd class="mt-1">{{ $assignment->effective_from->toDateString() }}</dd></div>
            <div><dt class="text-sm text-slate-400">Effective to</dt><dd class="mt-1">{{ $assignment->effective_to?->toDateString() ?? 'Open' }}</dd></div>
            <div><dt class="text-sm text-slate-400">Created by</dt><dd class="mt-1">{{ $assignment->creator?->name ?? 'System' }}</dd></div>
        </dl>
    </section>

    <section class="mt-5 rounded-2xl border border-white/10 bg-slate-900 p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="font-semibold">{{ __('Referred customers') }}</h2>
                <p class="mt-1 text-sm text-slate-400">
                    {{ __('Customers credited as originally brought in by this salesman. Ordinary assignments are not included here.') }}
                </p>
            </div>
            <span class="rounded-full bg-emerald-500/10 px-3 py-1 text-xs font-semibold text-emerald-300">
                {{ $referredCustomers->count() }}
            </span>
        </div>

        <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            @forelse($referredCustomers as $customer)
                <a href="{{ route('admin.customers.show', $customer) }}" class="rounded-xl border border-white/10 bg-slate-950 p-4 hover:border-emerald-400/30">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate font-semibold">{{ $customer->name }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $customer->code }}</p>
                        </div>
                        @if((int) $customer->assigned_salesman_id === (int) $assignment->salesman_id)
                            <span class="shrink-0 rounded-full bg-indigo-500/10 px-2 py-1 text-[10px] font-semibold uppercase text-indigo-300">
                                {{ __('Assigned') }}
                            </span>
                        @endif
                    </div>
                </a>
            @empty
                <div class="rounded-xl border border-dashed border-white/10 p-5 text-sm text-slate-500 md:col-span-2 xl:col-span-3">
                    {{ __('No customers have been marked as referred by this salesman.') }}
                </div>
            @endforelse
        </div>
    </section>
</x-layouts.app>
