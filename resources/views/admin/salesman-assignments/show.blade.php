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
</x-layouts.app>
