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

    <section class="mt-6">
        <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-lg font-bold">{{ __('Referral portfolio') }}</h2>
                <p class="mt-1 text-sm text-slate-400">
                    {{ __('Customers originally referred by this salesman. Assignment can change without changing referral ownership.') }}
                </p>
            </div>
            <span class="rounded-full bg-emerald-500/10 px-3 py-1 text-xs font-semibold text-emerald-300">
                {{ $referralSummary['customers'] }} {{ __('customers') }}
            </span>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
            @foreach([
                [__('Referred customers'), $referralSummary['customers']],
                [__('Active customers'), $referralSummary['active_customers']],
                [__('Orders'), $referralSummary['orders']],
                [__('Collections'), $referralSummary['collections']],
                [__('Visits'), $referralSummary['visits']],
            ] as [$label, $value])
                <div class="rounded-2xl border border-white/10 bg-slate-900 p-4">
                    <p class="text-xs uppercase tracking-wide text-slate-500">{{ $label }}</p>
                    <p class="mt-2 text-2xl font-bold">{{ number_format($value) }}</p>
                </div>
            @endforeach
        </div>

        <div class="mt-4 grid gap-4 lg:grid-cols-2 2xl:grid-cols-3">
            @forelse($referredCustomers as $customer)
                @php
                    $balance = $referralBalances->get($customer->uuid);
                    $balances = collect($balance['balances'] ?? []);
                @endphp
                <a href="{{ route('admin.customers.show', $customer) }}" class="rounded-2xl border border-white/10 bg-slate-900 p-5 transition hover:border-emerald-400/30 hover:bg-slate-800/80">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate font-semibold">{{ $customer->name }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $customer->code }} · {{ $customer->territory?->name ?? __('No territory') }}</p>
                        </div>
                        @if((int) $customer->assigned_salesman_id === (int) $salesman->id)
                            <span class="shrink-0 rounded-full bg-indigo-500/10 px-2 py-1 text-[10px] font-semibold uppercase text-indigo-300">{{ __('Assigned to me') }}</span>
                        @elseif($customer->assignedSalesman)
                            <span class="shrink-0 rounded-full bg-amber-500/10 px-2 py-1 text-[10px] font-semibold text-amber-300">{{ __('Assigned elsewhere') }}</span>
                        @endif
                    </div>

                    <div class="mt-4 grid grid-cols-3 gap-2 text-center text-xs">
                        <div class="rounded-xl bg-slate-950 p-3">
                            <p class="text-slate-500">{{ __('Orders') }}</p>
                            <p class="mt-1 text-lg font-bold">{{ $customer->orders_count }}</p>
                        </div>
                        <div class="rounded-xl bg-slate-950 p-3">
                            <p class="text-slate-500">{{ __('Collections') }}</p>
                            <p class="mt-1 text-lg font-bold">{{ $customer->collections_count }}</p>
                        </div>
                        <div class="rounded-xl bg-slate-950 p-3">
                            <p class="text-slate-500">{{ __('Visits') }}</p>
                            <p class="mt-1 text-lg font-bold">{{ $customer->visits_count }}</p>
                        </div>
                    </div>

                    <div class="mt-4 border-t border-white/10 pt-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Outstanding') }}</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @forelse($balances as $row)
                                <span class="rounded-lg bg-rose-500/10 px-2.5 py-1 text-xs font-semibold text-rose-200">
                                    {{ $row['currency'] }} {{ number_format((float) $row['outstanding_balance'], 2) }}
                                </span>
                            @empty
                                <span class="text-xs text-emerald-300">{{ __('No credit outstanding') }}</span>
                            @endforelse
                        </div>
                        @if($customer->assignedSalesman && (int) $customer->assigned_salesman_id !== (int) $salesman->id)
                            <p class="mt-3 text-xs text-slate-500">{{ __('Current assignment') }}: {{ $customer->assignedSalesman->full_name }}</p>
                        @endif
                    </div>
                </a>
            @empty
                <div class="rounded-2xl border border-dashed border-white/10 bg-slate-900 p-8 text-sm text-slate-400 lg:col-span-2 2xl:col-span-3">
                    {{ __('No customers are currently attributed as referrals to this salesman.') }}
                </div>
            @endforelse
        </div>

        @if($referredCustomers->hasPages())
            <div class="mt-5">{{ $referredCustomers->links() }}</div>
        @endif
    </section>
</x-layouts.app>
