<?php

namespace App\Services;

use App\Models\Collection;
use App\Models\Customer;
use App\Models\CustomerFollowUp;
use App\Models\CustomerVisit;
use App\Models\Order;
use App\Models\Salesman;
use App\Models\User;
use App\Models\WorkSession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class AiInsightsService
{
    public function snapshot(User $user): array
    {
        $tenant = $user->loadMissing('tenant')->tenant;
        $timezone = $tenant?->timezone ?: config('app.timezone', 'UTC');
        $now = CarbonImmutable::now($timezone);
        $today = $now->toDateString();
        $todayStartUtc = $now->startOfDay()->utc();
        $todayEndUtc = $now->endOfDay()->utc();
        $weekStartUtc = $now->subDays(6)->startOfDay()->utc();
        $staleCutoffUtc = $now->subDays(30)->startOfDay()->utc();

        $activeSalesmen = Salesman::active()->count();
        $startedToday = WorkSession::query()
            ->whereDate('date', $today)
            ->distinct('salesman_id')
            ->count('salesman_id');

        $salesByCurrency = Order::query()
            ->selectRaw('currency, SUM(grand_total) as total')
            ->where('status', 'approved')
            ->whereBetween('ordered_at', [$weekStartUtc, $todayEndUtc])
            ->groupBy('currency')
            ->pluck('total', 'currency')
            ->map(fn ($value) => round((float) $value, 2))
            ->all();

        $collectionsByCurrency = Collection::query()
            ->selectRaw('currency, SUM(amount) as total')
            ->where('status', 'verified')
            ->whereBetween('collected_at', [$weekStartUtc, $todayEndUtc])
            ->groupBy('currency')
            ->pluck('total', 'currency')
            ->map(fn ($value) => round((float) $value, 2))
            ->all();

        $snapshot = [
            'generated_at' => $now->toIso8601String(),
            'date' => $today,
            'active_salesmen' => $activeSalesmen,
            'salesmen_started_today' => $startedToday,
            'salesmen_not_started_today' => max(0, $activeSalesmen - $startedToday),
            'active_customers' => Customer::active()->count(),
            'customers_not_visited_30_days' => Customer::active()
                ->whereDoesntHave(
                    'visits',
                    fn ($query) => $query->where('checked_in_at', '>=', $staleCutoffUtc),
                )
                ->count(),
            'visits_today' => CustomerVisit::query()
                ->whereBetween('checked_in_at', [$todayStartUtc, $todayEndUtc])
                ->count(),
            'overdue_followups' => CustomerFollowUp::query()
                ->where('status', 'pending')
                ->where('due_at', '<', now())
                ->count(),
            'high_priority_followups' => CustomerFollowUp::query()
                ->where('status', 'pending')
                ->where('priority', 'high')
                ->count(),
            'pending_orders' => Order::query()->where('status', 'pending')->count(),
            'pending_collections' => Collection::query()->where('status', 'pending')->count(),
            'approved_sales_7d' => $salesByCurrency,
            'verified_collections_7d' => $collectionsByCurrency,
        ];

        $snapshot['recommendations'] = $this->recommendations($snapshot);

        return $snapshot;
    }

    public function answer(User $user, string $question): array
    {
        $snapshot = $this->snapshot($user);
        $providerEnabled = (bool) config('ai.enabled', false);
        $endpoint = trim((string) config('ai.endpoint'));

        if ($providerEnabled && $endpoint !== '') {
            $answer = $this->providerAnswer($question, $snapshot);

            if ($answer !== null) {
                return [
                    'answer' => $answer,
                    'source' => 'configured_ai_provider',
                    'snapshot' => $snapshot,
                ];
            }
        }

        return [
            'answer' => $this->localAnswer($question, $snapshot),
            'source' => 'fieldpulse_grounded_rules',
            'snapshot' => $snapshot,
        ];
    }

    private function recommendations(array $snapshot): array
    {
        $items = [];

        if ($snapshot['overdue_followups'] > 0) {
            $items[] = [
                'severity' => 'high',
                'title' => 'Clear overdue follow-ups',
                'message' => ':count customer follow-up(s) are already overdue.',
                'message_params' => ['count' => $snapshot['overdue_followups']],
                'action' => 'Open Follow-ups and assign or complete the overdue items.',
            ];
        }

        if ($snapshot['customers_not_visited_30_days'] > 0) {
            $items[] = [
                'severity' => 'high',
                'title' => 'Recover stale customer coverage',
                'message' => ':count active customer(s) have not been visited in the last 30 days.',
                'message_params' => [
                    'count' => $snapshot['customers_not_visited_30_days'],
                ],
                'action' => 'Use Smart Route / Daily Planner to prioritize stale accounts.',
            ];
        }

        if ($snapshot['pending_orders'] > 0) {
            $items[] = [
                'severity' => 'medium',
                'title' => 'Review pending orders',
                'message' => ':count order(s) are waiting for review.',
                'message_params' => ['count' => $snapshot['pending_orders']],
                'action' => 'Approve, reject, or investigate pending orders.',
            ];
        }

        if ($snapshot['pending_collections'] > 0) {
            $items[] = [
                'severity' => 'medium',
                'title' => 'Verify pending collections',
                'message' => ':count collection(s) are awaiting verification.',
                'message_params' => ['count' => $snapshot['pending_collections']],
                'action' => 'Verify payment evidence and reconcile customer balances.',
            ];
        }

        if ($snapshot['salesmen_not_started_today'] > 0) {
            $items[] = [
                'severity' => 'medium',
                'title' => 'Check field attendance',
                'message' => ':count active salesman/salesmen have no work session today.',
                'message_params' => [
                    'count' => $snapshot['salesmen_not_started_today'],
                ],
                'action' => 'Confirm leave, device issues, or delayed Start Day.',
            ];
        }

        if ($snapshot['high_priority_followups'] > 0) {
            $items[] = [
                'severity' => 'medium',
                'title' => 'Protect high-priority opportunities',
                'message' => ':count high-priority follow-up(s) remain open.',
                'message_params' => [
                    'count' => $snapshot['high_priority_followups'],
                ],
                'action' => 'Schedule them into today\'s customer plan.',
            ];
        }

        if ($items === []) {
            $items[] = [
                'severity' => 'low',
                'title' => 'No immediate operational exception',
                'message' => 'FieldPulse found no overdue follow-up, stale-coverage, pending-review, or attendance exception.',
                'action' => 'Continue monitoring route execution, sales, and collections.',
            ];
        }

        return $items;
    }

    private function providerAnswer(string $question, array $snapshot): ?string
    {
        try {
            $request = Http::timeout(max(1, (int) config('ai.timeout_seconds', 20)))
                ->acceptJson();

            $bearer = trim((string) config('ai.bearer_token'));
            if ($bearer !== '') {
                $request = $request->withToken($bearer);
            }

            $response = $request->post((string) config('ai.endpoint'), [
                'model' => config('ai.model'),
                'question' => trim($question),
                'snapshot' => collect($snapshot)->except('recommendations')->all(),
                'locale' => app()->getLocale(),
                'instructions' => 'Answer only from the supplied FieldPulse aggregate snapshot. Do not invent customer-level details, names, forecasts, or facts that are not present. Respond in the requested locale when supported.',
            ]);

            if (! $response->successful()) {
                return null;
            }

            $answer = $response->json('answer')
                ?? $response->json('output')
                ?? $response->json('text');

            return is_string($answer) && trim($answer) !== ''
                ? trim($answer)
                : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function localAnswer(string $question, array $snapshot): string
    {
        $question = Str::lower(trim($question));

        if (Str::contains($question, ['sale', 'revenue', 'order'])) {
            return __('Approved sales in the last 7 days: :sales. Pending orders: :pending.', [
                'sales' => $this->currencySummary($snapshot['approved_sales_7d']),
                'pending' => $snapshot['pending_orders'],
            ]);
        }

        if (Str::contains($question, ['collection', 'payment', 'cash'])) {
            return __('Verified collections in the last 7 days: :collections. Pending collections: :pending.', [
                'collections' => $this->currencySummary($snapshot['verified_collections_7d']),
                'pending' => $snapshot['pending_collections'],
            ]);
        }

        if (Str::contains($question, ['visit', 'customer', 'coverage'])) {
            return __('Visits today: :visits. Active customers without a visit in 30 days: :customers.', [
                'visits' => $snapshot['visits_today'],
                'customers' => $snapshot['customers_not_visited_30_days'],
            ]);
        }

        if (Str::contains($question, ['follow', 'task', 'priority'])) {
            return __('Overdue follow-ups: :overdue. Open high-priority follow-ups: :high.', [
                'overdue' => $snapshot['overdue_followups'],
                'high' => $snapshot['high_priority_followups'],
            ]);
        }

        if (Str::contains($question, ['attendance', 'start day', 'salesman', 'team'])) {
            return __(':started of :active active salesmen have started work today.', [
                'started' => $snapshot['salesmen_started_today'],
                'active' => $snapshot['active_salesmen'],
            ]);
        }

        $top = $snapshot['recommendations'][0];

        return __($top['title']).': '
            .__($top['message'], $top['message_params'] ?? [])
            .' '.__($top['action']);
    }

    private function currencySummary(array $values): string
    {
        if ($values === []) {
            return __('none recorded');
        }

        return collect($values)
            ->map(fn ($value, $currency) => $currency.' '.number_format((float) $value, 2))
            ->implode(', ');
    }
}
