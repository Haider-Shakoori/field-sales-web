<x-layouts.app>
    <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <h1 class="text-2xl font-bold">Customer visits</h1>
            <p class="mt-1 text-sm text-slate-400">Schedule visits, review check-ins, geofence results, outcomes and suspicious activity.</p>
        </div>
        @can('visits:manage')
            <div class="flex flex-wrap gap-2">
                @can('sales-team:view')
                    <a href="{{ route('admin.daily-planner.index') }}" class="rounded-xl bg-white/10 px-4 py-3 font-semibold text-slate-100 hover:bg-white/15">Auto schedule</a>
                @endcan
                <button type="button" onclick="document.getElementById('bulk-visit-modal').classList.remove('hidden')" class="rounded-xl bg-white/10 px-4 py-3 font-semibold text-slate-100 hover:bg-white/15">Bulk assign</button>
                <button type="button" onclick="document.getElementById('add-visit-modal').classList.remove('hidden')" class="rounded-xl bg-indigo-500 px-4 py-3 font-semibold text-white">+ Add visit</button>
            </div>
        @endcan
    </div>

    @if(session('success'))
        <div class="mb-5 rounded-xl border border-emerald-400/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-5 rounded-xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-sm text-rose-200">{{ $errors->first() }}</div>
    @endif

    @if($upcomingAssignments->isNotEmpty())
        <div class="mb-5 rounded-2xl border border-white/10 bg-slate-900 p-4">
            <div class="mb-3 flex items-center justify-between">
                <div><h2 class="font-semibold">Upcoming assigned visits</h2><p class="text-xs text-slate-400">These appear in the salesman's mobile app on the scheduled date.</p></div>
                <span class="rounded-full bg-indigo-500/15 px-3 py-1 text-xs text-indigo-200">{{ $upcomingAssignments->count() }} upcoming</span>
            </div>
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">                @foreach($upcomingAssignments as $assignment)
                    <div class="rounded-xl border border-white/10 bg-slate-950/60 p-4">
                        <div class="font-semibold">{{ $assignment->customer?->name ?? 'Customer' }}</div>
                        <div class="mt-1 text-xs text-slate-400">{{ $assignment->salesman?->full_name ?? $assignment->salesman?->user?->name ?? 'Salesman' }}</div>
                        <div class="mt-2 text-sm">{{ $assignment->visit_date?->format('Y-m-d') }} @if($assignment->scheduled_time) · {{ substr($assignment->scheduled_time, 0, 5) }} @endif · {{ str($assignment->purpose)->replace('_', ' ')->title() }}</div>
                        @can('visits:manage')
                            <form method="POST" action="{{ route('admin.visit-assignments.destroy', $assignment) }}" class="mt-3" onsubmit="return confirm('Cancel this visit assignment?')">
                                @csrf @method('DELETE')
                                <button class="text-xs font-semibold text-rose-300">Cancel assignment</button>
                            </form>
                        @endcan
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <form class="mb-5 flex flex-wrap gap-3">
        <select name="status" class="rounded-xl border border-white/10 bg-slate-900 px-4 py-3">
            <option value="">All statuses</option>
            <option value="active" @selected($status === 'active')>Active</option>
            <option value="completed" @selected($status === 'completed')>Completed</option>
        </select>
        <label class="flex items-center gap-2 rounded-xl border border-white/10 bg-slate-900 px-4 py-3">
            <input type="checkbox" name="flagged" value="1" @checked($flagged)> Unreviewed flags only
        </label>        <button class="rounded-xl bg-indigo-500 px-4 py-3 font-semibold">Filter</button>
    </form>

    <div class="overflow-hidden rounded-2xl border border-white/10 bg-slate-900">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-white/5"><tr><th class="px-5 py-3">Customer</th><th class="px-5 py-3">Salesman</th><th class="px-5 py-3">Type</th><th class="px-5 py-3">Check-in</th><th class="px-5 py-3">Outcome</th><th class="px-5 py-3">Flags</th><th></th></tr></thead>
                <tbody class="divide-y divide-white/10">
                @forelse($visits as $visit)
                    <tr>
                        <td class="px-5 py-4">{{ $visit->customer?->name ?? 'Deleted customer' }}</td>
                        <td class="px-5 py-4">{{ $visit->salesman?->full_name ?? $visit->salesman?->user?->name ?? '—' }}</td>
                        <td class="px-5 py-4">{{ $visit->is_planned ? 'Planned' : 'Unplanned' }}</td>
                        <td class="px-5 py-4">{{ $visit->checked_in_at?->format('Y-m-d H:i') }}</td>
                        <td class="px-5 py-4">{{ $visit->outcome ? str($visit->outcome)->replace('_', ' ')->title() : ucfirst($visit->status) }}</td>
                        <td class="px-5 py-4">{{ $visit->suspicious_flags_count ?: '—' }}</td>
                        <td class="px-5 py-4 text-right"><a href="{{ route('admin.visits.show', $visit) }}" class="rounded-lg bg-white/10 px-3 py-2">View</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-10 text-center text-slate-400">No visits found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-5">{{ $visits->links() }}</div>
    @can('visits:manage')
        <div id="bulk-visit-modal" class="fixed inset-0 z-50 hidden overflow-y-auto bg-slate-950/80 p-4 backdrop-blur-sm">
            <div class="mx-auto mt-8 max-w-3xl rounded-2xl border border-white/10 bg-slate-900 shadow-2xl">
                <div class="flex items-center justify-between border-b border-white/10 px-6 py-4">
                    <div>
                        <h2 class="text-lg font-semibold">Bulk assign visits</h2>
                        <p class="text-xs text-slate-400">Schedule multiple customers for one salesman and date. Existing assignments are skipped.</p>
                    </div>
                    <button type="button" onclick="document.getElementById('bulk-visit-modal').classList.add('hidden')" class="rounded-lg bg-white/5 px-3 py-2">✕</button>
                </div>
                <form method="POST" action="{{ route('admin.visit-assignments.bulk') }}" class="grid gap-4 p-6 md:grid-cols-2">
                    @csrf
                    <label>
                        <span class="mb-1 block text-sm">Salesman</span>
                        <select name="salesman_id" required class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-3">
                            <option value="">Select salesman</option>
                            @foreach($salesmen as $salesman)
                                <option value="{{ $salesman->id }}">{{ $salesman->full_name ?: $salesman->user?->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        <span class="mb-1 block text-sm">Visit date</span>
                        <input type="date" name="visit_date" value="{{ now()->toDateString() }}" required class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-3">
                    </label>
                    <div class="md:col-span-2">
                        <label for="bulk-customer-search" class="mb-1 block text-sm">Customers</label>
                        <input id="bulk-customer-search" type="search" placeholder="Search customer name, code or address…" class="mb-2 w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-3" oninput="filterBulkVisitCustomers(this.value)">
                        <select id="bulk-customer-select" name="customer_ids[]" multiple required size="10" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-3">
                            @foreach($customers as $customer)
                                <option value="{{ $customer->id }}" data-search="{{ strtolower($customer->name.' '.$customer->code.' '.$customer->address) }}">
                                    {{ $customer->name }}{{ $customer->code ? ' · '.$customer->code : '' }}{{ $customer->address ? ' · '.$customer->address : '' }}
                                </option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-slate-500">Use Ctrl/Cmd or Shift to select multiple customers. Maximum 100 per assignment.</p>
                    </div>
                    <label>
                        <span class="mb-1 block text-sm">Start time (optional)</span>
                        <input type="time" name="scheduled_time" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-3">
                    </label>
                    <label>
                        <span class="mb-1 block text-sm">Minutes between visits</span>
                        <input type="number" name="time_interval_minutes" min="5" max="240" value="20" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-3">
                    </label>
                    <label>
                        <span class="mb-1 block text-sm">Purpose</span>
                        <select name="purpose" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-3">
                            <option value="sales">Sales</option>
                            <option value="collection">Collection</option>
                            <option value="follow_up">Follow-up</option>
                            <option value="merchandising">Merchandising</option>
                            <option value="survey">Survey</option>
                            <option value="other">Other</option>
                        </select>
                    </label>
                    <label>
                        <span class="mb-1 block text-sm">Priority</span>
                        <select name="priority" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-3">
                            <option value="normal">Normal</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </label>
                    <label>
                        <span class="mb-1 block text-sm">Expected visit duration (minutes)</span>
                        <input type="number" name="expected_duration_minutes" min="5" max="480" value="15" required class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-3">
                    </label>
                    <label class="md:col-span-2">
                        <span class="mb-1 block text-sm">Notes</span>
                        <textarea name="notes" rows="3" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-3"></textarea>
                    </label>
                    <div class="flex justify-end gap-3 md:col-span-2">
                        <button type="button" onclick="document.getElementById('bulk-visit-modal').classList.add('hidden')" class="rounded-xl bg-white/5 px-4 py-3">Cancel</button>
                        <button class="rounded-xl bg-indigo-500 px-5 py-3 font-semibold">Assign selected visits</button>
                    </div>
                </form>
            </div>
        </div>

        <div id="add-visit-modal" class="fixed inset-0 z-50 hidden overflow-y-auto bg-slate-950/80 p-4 backdrop-blur-sm">
            <div class="mx-auto mt-8 max-w-2xl rounded-2xl border border-white/10 bg-slate-900 shadow-2xl">
                <div class="flex items-center justify-between border-b border-white/10 px-6 py-4">
                    <div><h2 class="text-lg font-semibold">Assign customer visit</h2><p class="text-xs text-slate-400">This will appear in the salesman's mobile Today list.</p></div>
                    <button type="button" onclick="document.getElementById('add-visit-modal').classList.add('hidden')" class="rounded-lg bg-white/5 px-3 py-2">✕</button>
                </div>
                <form method="POST" action="{{ route('admin.visits.store') }}" class="grid gap-4 p-6 md:grid-cols-2">
                    @csrf
                    <label><span class="mb-1 block text-sm">Salesman</span><select name="salesman_id" required class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-3"><option value="">Select salesman</option>@foreach($salesmen as $salesman)<option value="{{ $salesman->id }}">{{ $salesman->full_name ?: $salesman->user?->name }}</option>@endforeach</select></label>
                    <label><span class="mb-1 block text-sm">Customer</span><select name="customer_id" required class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-3"><option value="">Select customer</option>@foreach($customers as $customer)<option value="{{ $customer->id }}">{{ $customer->name }}{{ $customer->code ? ' · '.$customer->code : '' }}</option>@endforeach</select></label>
                    <label><span class="mb-1 block text-sm">Visit date</span><input type="date" name="visit_date" value="{{ now()->toDateString() }}" required class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-3"></label>
                    <label><span class="mb-1 block text-sm">Time (optional)</span><input type="time" name="scheduled_time" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-3"></label>
                    <label><span class="mb-1 block text-sm">Purpose</span><select name="purpose" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-3"><option value="sales">Sales</option><option value="collection">Collection</option><option value="follow_up">Follow-up</option><option value="merchandising">Merchandising</option><option value="survey">Survey</option><option value="other">Other</option></select></label>
                    <label><span class="mb-1 block text-sm">Priority</span><select name="priority" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-3"><option value="normal">Normal</option><option value="high">High</option><option value="urgent">Urgent</option></select></label>
                    <label><span class="mb-1 block text-sm">Expected duration (minutes)</span><input type="number" name="expected_duration_minutes" min="5" max="480" value="15" required class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-3"></label>
                    <label class="md:col-span-2"><span class="mb-1 block text-sm">Notes</span><textarea name="notes" rows="3" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-3"></textarea></label>
                    <div class="flex justify-end gap-3 md:col-span-2"><button type="button" onclick="document.getElementById('add-visit-modal').classList.add('hidden')" class="rounded-xl bg-white/5 px-4 py-3">Cancel</button><button class="rounded-xl bg-indigo-500 px-5 py-3 font-semibold">Assign visit</button></div>
                </form>
            </div>
        </div>
    @endcan
    <script>
        function filterBulkVisitCustomers(value) {
            const query = (value || '').trim().toLowerCase();
            document.querySelectorAll('#bulk-customer-select option').forEach((option) => {
                option.hidden = query !== '' && !option.dataset.search.includes(query);
            });
        }
    </script>
</x-layouts.app>
