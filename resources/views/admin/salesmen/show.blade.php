<x-layouts.app>
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold">{{ $salesman->full_name }}</h1>
            <p class="mt-1 text-sm text-slate-400">{{ $salesman->employee_code }} · {{ $salesman->user?->email }}</p>
        </div>
        @if(auth()->user()->hasPermission('sales-team:manage'))
            <a href="{{ route('admin.salesmen.edit', $salesman) }}" class="rounded-xl bg-indigo-500 px-4 py-2.5 font-semibold">Edit</a>
        @endif
    </div>

    <div class="grid gap-5 lg:grid-cols-2">
        <section class="rounded-2xl border border-white/10 bg-slate-900 p-5">
            <h2 class="font-semibold">Devices</h2>
            <div class="mt-4 space-y-3">
                @forelse($salesman->devices as $device)
                    <a href="{{ route('admin.devices.show', $device) }}" class="block rounded-xl bg-slate-950 p-4 hover:bg-slate-800">
                        <div class="font-medium">{{ $device->device_model ?? 'Android device' }}</div>
                        <div class="mt-1 text-sm text-slate-400">{{ $device->installation_uuid }}</div>
                        <div class="mt-2 text-xs">{{ $device->isRevoked() ? 'Revoked' : 'Active' }} · last seen {{ $device->last_seen_at?->diffForHumans() ?? 'never' }}</div>
                    </a>
                @empty
                    <p class="text-sm text-slate-400">No registered devices.</p>
                @endforelse
            </div>
        </section>

        <section class="rounded-2xl border border-white/10 bg-slate-900 p-5">
            <h2 class="font-semibold">Assignment history</h2>
            <div class="mt-4 space-y-3">
                @forelse($salesman->assignments as $assignment)
                    <a href="{{ route('admin.salesman-assignments.show', $assignment) }}" class="block rounded-xl bg-slate-950 p-4 hover:bg-slate-800">
                        <div>{{ $assignment->branch?->name ?? 'No branch' }}</div>
                        <div class="mt-1 text-sm text-slate-400">
                            {{ $assignment->effective_from->toDateString() }} → {{ $assignment->effective_to?->toDateString() ?? 'Current' }}
                        </div>
                    </a>
                @empty
                    <p class="text-sm text-slate-400">No assignment history.</p>
                @endforelse
            </div>
        </section>
    </div>
</x-layouts.app>
