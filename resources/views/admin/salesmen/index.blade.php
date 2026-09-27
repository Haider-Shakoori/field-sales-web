<x-layouts.app>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold">Salesmen</h1>
            <p class="mt-1 text-sm text-slate-400">Profiles, active device state, and current assignment.</p>
        </div>
        @if(auth()->user()->hasPermission('sales-team:manage'))
            <a href="{{ route('admin.salesmen.create') }}" class="rounded-xl bg-indigo-500 px-4 py-2.5 font-semibold hover:bg-indigo-400">Add salesman</a>
        @endif
    </div>

    <div class="overflow-hidden rounded-2xl border border-white/10 bg-slate-900">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-white/5 text-slate-300">
                <tr>
                    <th class="px-5 py-3">Salesman</th>
                    <th class="px-5 py-3">Code</th>
                    <th class="px-5 py-3">Branch / assignment</th>
                    <th class="px-5 py-3">Device</th>
                    <th class="px-5 py-3">Status</th>
                    <th class="px-5 py-3 text-right">Actions</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-white/10">
                @forelse($salesmen as $salesman)
                    @php
                        $current = $salesman->assignments->first();
                        $activeDevice = $salesman->devices->first(fn ($device) => $device->is_active && ! $device->revoked_at);
                    @endphp
                    <tr>
                        <td class="px-5 py-4">
                            <div class="font-medium">{{ $salesman->full_name }}</div>
                            <div class="text-slate-400">{{ $salesman->user?->email }}</div>
                        </td>
                        <td class="px-5 py-4">{{ $salesman->employee_code }}</td>
                        <td class="px-5 py-4">
                            <div>{{ $current?->branch?->name ?? $salesman->user?->branch?->name ?? 'Unassigned' }}</div>
                            @if($current?->supervisor)
                                <div class="text-xs text-slate-400">Supervisor: {{ $current->supervisor->full_name }}</div>
                            @endif
                        </td>
                        <td class="px-5 py-4">{{ $activeDevice ? 'Active' : 'No active device' }}</td>
                        <td class="px-5 py-4">{{ $salesman->is_active ? 'Active' : 'Inactive' }}</td>
                        <td class="px-5 py-4 text-right">
                            <div class="flex justify-end gap-2">
                                <a href="{{ route('admin.salesmen.show', $salesman) }}" class="rounded-lg bg-white/10 px-3 py-2 hover:bg-white/20">View</a>
                                @if(auth()->user()->hasAnyRole(['owner', 'company_admin', 'sales_manager', 'supervisor']) && $salesman->is_active && $salesman->user?->is_active)
                                    <button type="button" onclick="document.getElementById('notify-salesman-{{ $salesman->id }}').showModal()" class="rounded-lg bg-indigo-500/20 px-3 py-2 font-medium text-indigo-200 hover:bg-indigo-500/30">Notify</button>
                                @endif
                            </div>
                            @if(auth()->user()->hasAnyRole(['owner', 'company_admin', 'sales_manager', 'supervisor']) && $salesman->is_active && $salesman->user?->is_active)
                                <dialog id="notify-salesman-{{ $salesman->id }}" class="w-full max-w-lg rounded-2xl border border-white/10 bg-slate-900 p-0 text-left text-slate-100 shadow-2xl backdrop:bg-black/70">
                                    <form method="POST" action="{{ route('admin.salesmen.notify', $salesman) }}" class="p-6">
                                        @csrf
                                        <div class="flex items-start justify-between gap-4">
                                            <div>
                                                <h2 class="text-lg font-bold">Notify {{ $salesman->full_name }}</h2>
                                                <p class="mt-1 text-sm text-slate-400">Send an operational message to this salesman's FieldPulse app.</p>
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
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-10 text-center text-slate-400">No salesman profiles found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-5">{{ $salesmen->links() }}</div>
</x-layouts.app>
