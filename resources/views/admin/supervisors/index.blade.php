<x-layouts.app>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold">Supervisors</h1>
            <p class="mt-1 text-sm text-slate-400">Supervisor profiles and current coverage.</p>
        </div>
        @if(auth()->user()->hasPermission('sales-team:manage'))
            <a href="{{ route('admin.supervisors.create') }}" class="rounded-xl bg-indigo-500 px-4 py-2.5 font-semibold">Add supervisor</a>
        @endif
    </div>
    <div class="grid gap-4 lg:grid-cols-2">
        @forelse($supervisors as $supervisor)
            <a href="{{ route('admin.supervisors.show', $supervisor) }}" class="rounded-2xl border border-white/10 bg-slate-900 p-5 hover:bg-slate-800">
                <div class="font-semibold">{{ $supervisor->full_name }}</div>
                <div class="mt-1 text-sm text-slate-400">{{ $supervisor->employee_code }} · {{ $supervisor->user?->email }}</div>
                <div class="mt-3 text-sm">{{ $supervisor->assignments->first()?->branch?->name ?? 'No current branch assignment' }}</div>
            </a>
        @empty
            <div class="rounded-2xl border border-white/10 bg-slate-900 p-8 text-slate-400">No supervisor profiles found.</div>
        @endforelse
    </div>
    <div class="mt-5">{{ $supervisors->links() }}</div>
</x-layouts.app>
