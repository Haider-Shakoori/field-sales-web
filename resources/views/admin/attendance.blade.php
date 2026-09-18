<x-layouts.app>
<div class="space-y-5">
  <div><h1 class="text-3xl font-black">Attendance</h1><p class="mt-1 text-sm text-slate-500">Work sessions with actual start/end timestamps and device context.</p></div>
  <form class="flex flex-wrap gap-3 rounded-2xl border border-slate-200 bg-white p-4 dark:border-white/10 dark:bg-slate-900">
    <input type="date" name="date" value="{{ request('date') }}" class="rounded-xl border border-slate-200 bg-transparent px-3 py-2 dark:border-white/10">
    <select name="status" class="rounded-xl border border-slate-200 bg-transparent px-3 py-2 dark:border-white/10"><option value="">All statuses</option><option value="active" @selected(request('status')==='active')>Active</option><option value="completed" @selected(request('status')==='completed')>Completed</option></select>
    <button class="rounded-xl bg-indigo-600 px-4 py-2 font-semibold text-white">Filter</button>
  </form>
  <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white dark:border-white/10 dark:bg-slate-900">
    <table class="min-w-full text-sm"><thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 dark:bg-white/5"><tr><th class="p-4">Salesman</th><th class="p-4">Date</th><th class="p-4">Start</th><th class="p-4">End</th><th class="p-4">Duration</th><th class="p-4">Status</th><th class="p-4">Flags</th></tr></thead>
    <tbody class="divide-y divide-slate-100 dark:divide-white/10">
    @forelse($rows as $row)<tr><td class="p-4 font-semibold">{{ $row->salesman?->user?->name ?? '—' }}<div class="text-xs font-normal text-slate-500">{{ $row->salesman?->employee_code }}</div></td><td class="p-4">{{ $row->date?->format('Y-m-d') }}</td><td class="p-4">{{ $row->start_time?->format('Y-m-d H:i:s') }}</td><td class="p-4">{{ $row->end_time?->format('Y-m-d H:i:s') ?? '—' }}</td><td class="p-4">{{ $row->duration_minutes !== null ? $row->duration_minutes.' min' : '—' }}</td><td class="p-4"><span class="rounded-full bg-slate-100 px-2 py-1 text-xs dark:bg-white/10">{{ $row->status }}</span></td><td class="p-4">{{ $row->is_late_start ? 'Late ' : '' }}{{ $row->is_early_finish ? 'Early' : '' }}</td></tr>
    @empty<tr><td colspan="7" class="p-10 text-center text-slate-500">No attendance records match the filters.</td></tr>@endforelse
    </tbody></table>
  </div>
  {{ $rows->withQueryString()->links() }}
</div>
</x-layouts.app>
