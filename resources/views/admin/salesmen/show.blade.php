<x-layouts.app>
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold">{{ $salesman->full_name }}</h1>
            <p class="mt-1 text-sm text-slate-400">{{ $salesman->employee_code }} · {{ $salesman->user?->email }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if(auth()->user()->hasAnyRole(['owner', 'company_admin', 'sales_manager', 'supervisor']) && $salesman->is_active && $salesman->user?->is_active)
                <button type="button" onclick="document.getElementById('notify-salesman').showModal()" class="rounded-xl bg-emerald-500/20 px-4 py-2.5 font-semibold text-emerald-200 hover:bg-emerald-500/30">Send notification</button>
            @endif
            @if(auth()->user()->hasPermission('sales-team:manage'))
                <a href="{{ route('admin.salesmen.edit', $salesman) }}" class="rounded-xl bg-indigo-500 px-4 py-2.5 font-semibold">Edit</a>
            @endif
        </div>
    </div>

    @if(auth()->user()->hasAnyRole(['owner', 'company_admin', 'sales_manager', 'supervisor']) && $salesman->is_active && $salesman->user?->is_active)
        <dialog id="notify-salesman" class="w-full max-w-lg rounded-2xl border border-white/10 bg-slate-900 p-0 text-slate-100 shadow-2xl backdrop:bg-black/70">
            <form method="POST" action="{{ route('admin.salesmen.notify', $salesman) }}" class="p-6">
                @csrf
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-bold">Notify {{ $salesman->full_name }}</h2>
                        <p class="mt-1 text-sm text-slate-400">The message is saved in FieldPulse and pushed to the active Android device when an FCM token is registered.</p>
                    </div>
                    <button type="button" onclick="this.closest('dialog').close()" class="rounded-lg bg-white/10 px-3 py-1.5 text-slate-300 hover:bg-white/20">Close</button>
                </div>
                <textarea name="message" required minlength="2" maxlength="500" rows="4" class="mt-5 w-full rounded-xl border border-white/10 bg-slate-950 p-3" placeholder="Type the notification message...">Please continue with your assigned route and update your visit status.</textarea>
                <div class="mt-4 flex justify-end">
                    <button class="rounded-xl bg-indigo-500 px-4 py-2.5 font-semibold hover:bg-indigo-400">Send notification</button>
                </div>
            </form>
        </dialog>
    @endif

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
