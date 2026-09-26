<?php

namespace App\Services;

use App\Models\Collection as CustomerCollection;
use App\Models\CustomerVisit;
use App\Models\GamificationPoint;
use App\Models\Order;
use App\Models\Salesman;
use App\Models\Tenant;
use Illuminate\Support\Collection;

final class GamificationService
{
    public const POINTS = [
        'visit_completed' => 10,
        'planned_visit_completed' => 5,
        'order_approved' => 20,
        'collection_verified' => 20,
    ];

    public function __construct(
        private readonly FieldIntelligenceSettingsService $settings,
    ) {}

    public function enabled(Tenant $tenant): bool
    {
        return $this->settings->gamificationEnabled($tenant);
    }

    public function sync(Tenant $tenant): int
    {
        if (! $this->enabled($tenant)) {
            return 0;
        }

        $awarded = 0;

        CustomerVisit::query()
            ->where('status', 'completed')
            ->whereNotNull('checked_out_at')
            ->chunkById(200, function ($visits) use (&$awarded): void {
                foreach ($visits as $visit) {
                    $awarded += $this->award(
                        $visit->salesman_id,
                        'visit_completed',
                        CustomerVisit::class,
                        $visit->id,
                        self::POINTS['visit_completed'],
                        $visit->checked_out_at,
                    );

                    if ($visit->is_planned) {
                        $awarded += $this->award(
                            $visit->salesman_id,
                            'planned_visit_completed',
                            CustomerVisit::class,
                            $visit->id,
                            self::POINTS['planned_visit_completed'],
                            $visit->checked_out_at,
                        );
                    }
                }
            });

        Order::query()
            ->where('status', 'approved')
            ->chunkById(200, function ($orders) use (&$awarded): void {
                foreach ($orders as $order) {
                    $awarded += $this->award(
                        $order->salesman_id,
                        'order_approved',
                        Order::class,
                        $order->id,
                        self::POINTS['order_approved'],
                        $order->status_changed_at ?? $order->ordered_at,
                    );
                }
            });

        CustomerCollection::query()
            ->where('status', 'verified')
            ->chunkById(200, function ($collections) use (&$awarded): void {
                foreach ($collections as $collection) {
                    $awarded += $this->award(
                        $collection->salesman_id,
                        'collection_verified',
                        CustomerCollection::class,
                        $collection->id,
                        self::POINTS['collection_verified'],
                        $collection->status_changed_at ?? $collection->collected_at,
                    );
                }
            });

        return $awarded;
    }

    public function leaderboard(Tenant $tenant, int $days = 30): Collection
    {
        if (! $this->enabled($tenant)) {
            return collect();
        }

        $since = now($tenant->timezone)->subDays($days - 1)->startOfDay()->utc();

        $totals = GamificationPoint::query()
            ->selectRaw('salesman_id, SUM(points) as total_points, COUNT(*) as event_count, COUNT(DISTINCT DATE(earned_at)) as active_days')
            ->where('earned_at', '>=', $since)
            ->groupBy('salesman_id')
            ->orderByDesc('total_points')
            ->get()
            ->keyBy('salesman_id');

        return Salesman::query()
            ->active()
            ->whereIn('id', $totals->keys())
            ->get()
            ->map(function (Salesman $salesman) use ($totals): array {
                $score = $totals->get($salesman->id);
                $points = (int) $score->total_points;

                return [
                    'salesman' => $salesman,
                    'points' => $points,
                    'events' => (int) $score->event_count,
                    'active_days' => (int) $score->active_days,
                    'level' => $this->level($points),
                    'achievements' => $this->achievements($salesman->id),
                ];
            })
            ->sortByDesc('points')
            ->values()
            ->map(fn (array $row, int $index) => $row + ['rank' => $index + 1]);
    }

    private function award(
        int $salesmanId,
        string $eventType,
        string $sourceType,
        int $sourceId,
        int $points,
        mixed $earnedAt,
    ): int {
        $point = GamificationPoint::firstOrCreate(
            [
                'event_type' => $eventType,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
            ],
            [
                'salesman_id' => $salesmanId,
                'points' => $points,
                'earned_at' => $earnedAt ?? now(),
            ],
        );

        return $point->wasRecentlyCreated ? 1 : 0;
    }

    private function level(int $points): string
    {
        return match (true) {
            $points >= 1000 => 'Champion',
            $points >= 500 => 'Leader',
            $points >= 250 => 'Achiever',
            $points >= 100 => 'Momentum',
            default => 'Starter',
        };
    }

    private function achievements(int $salesmanId): array
    {
        $counts = GamificationPoint::query()
            ->where('salesman_id', $salesmanId)
            ->selectRaw('event_type, COUNT(*) as total')
            ->groupBy('event_type')
            ->pluck('total', 'event_type');

        return collect([
            'Visit Pro' => (int) ($counts['visit_completed'] ?? 0) >= 25,
            'Sales Builder' => (int) ($counts['order_approved'] ?? 0) >= 10,
            'Collection Closer' => (int) ($counts['collection_verified'] ?? 0) >= 10,
            'Route Discipline' => (int) ($counts['planned_visit_completed'] ?? 0) >= 20,
        ])->filter()->keys()->all();
    }
}
