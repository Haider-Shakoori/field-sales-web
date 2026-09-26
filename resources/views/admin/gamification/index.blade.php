<x-layouts.app title="Gamification">
    <div class="space-y-6">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wider text-indigo-300">Team engagement</p>
            <h1 class="mt-1 text-2xl font-bold text-white">Gamification</h1>
            <p class="mt-2 max-w-3xl text-sm text-slate-400">Recognition is calculated from verified FieldPulse activity. Replays and repeated synchronization cannot award the same event twice.</p>
        </div>

        @unless($enabled)
            <div class="rounded-2xl border border-amber-400/20 bg-amber-400/10 p-5">
                <p class="font-semibold text-amber-200">Gamification is turned off for this organization.</p>
                <p class="mt-1 text-sm text-amber-100/70">Enable it from Organization Settings when you want points and achievements to become active.</p>
            </div>
        @else
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                @foreach($rules as $event => $points)
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                        <p class="text-sm text-slate-400">{{ __(str($event)->replace('_', ' ')->title()) }}</p>
                        <p class="mt-2 text-2xl font-bold text-white">+{{ $points }}</p>
                        <p class="text-xs text-slate-500">verified points</p>
                    </div>
                @endforeach
            </div>

            <div class="overflow-hidden rounded-2xl border border-white/10 bg-slate-900/60">
                <div class="border-b border-white/10 px-5 py-4">
                    <h2 class="font-semibold text-white">30-day team leaderboard</h2>
                    <p class="mt-1 text-xs text-slate-400">Business outcomes only. No points are awarded for taps, app opens, or unverified submissions.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-white/10 text-sm">
                        <thead class="bg-white/5 text-left text-xs uppercase tracking-wider text-slate-400">
                            <tr><th class="px-5 py-3">Rank</th><th class="px-5 py-3">Salesman</th><th class="px-5 py-3">Points</th><th class="px-5 py-3">Level</th><th class="px-5 py-3">Active days</th><th class="px-5 py-3">Achievements</th></tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            @forelse($leaderboard as $row)
                                <tr>
                                    <td class="px-5 py-4 font-semibold text-indigo-200">#{{ $row['rank'] }}</td>
                                    <td class="px-5 py-4"><p class="font-medium text-white">{{ $row['salesman']->full_name }}</p><p class="text-xs text-slate-500">{{ $row['salesman']->employee_code }}</p></td>
                                    <td class="px-5 py-4 font-bold text-white">{{ number_format($row['points']) }}</td>
                                    <td class="px-5 py-4 text-slate-300">{{ $row['level'] }}</td>
                                    <td class="px-5 py-4 text-slate-300">{{ $row['active_days'] }}</td>
                                    <td class="px-5 py-4 text-slate-300">{{ $row['achievements'] ? implode(' · ', $row['achievements']) : '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-5 py-10 text-center text-slate-500">No verified gamification activity yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endunless
    </div>
</x-layouts.app>
