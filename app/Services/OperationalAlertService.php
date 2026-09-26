<?php

namespace App\Services;

use App\Models\OperationalNotification;
use App\Models\Salesman;
use App\Models\SalesmanAssignment;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;

final class OperationalAlertService
{
    public function __construct(
        private readonly ManagementIntelligenceService $intelligence,
        private readonly NotificationService $notifications,
    ) {}

    public function sendMissedVisitAlerts(Tenant $tenant): int
    {
        $timezone = $tenant->timezone ?: config('app.timezone', 'UTC');
        $date = CarbonImmutable::now($timezone)->toDateString();
        $actor = User::query()->where('is_active', true)
            ->whereIn('role', ['owner', 'company_admin', 'sales_manager'])
            ->first();

        if (! $actor) {
            return 0;
        }

        $rows = $this->intelligence->build($actor, $date)['rows'];
        $sent = 0;

        foreach ($rows as $row) {
            $missed = (int) $row['route']['missed_stops'];

            if ($missed < 1 || ! $row['salesman']?->user?->is_active) {
                continue;
            }

            $key = "missed-visits:{$date}:{$row['salesman']->id}";

            if ($this->alreadySent($row['salesman']->user, $key)) {
                continue;
            }

            $this->notifications->notifySafely(
                $row['salesman']->user,
                'route.missed_visits',
                'route_alerts',
                'Planned visits need attention',
                "You have {$missed} planned visit(s) marked as missed. Review your route and follow up where required.",
                ['dedupe_key' => $key, 'date' => $date, 'missed_visits' => $missed],
                'high',
            );
            $sent++;
        }

        return $sent;
    }

    public function nudge(User $actor, Salesman $salesman, string $message): void
    {
        abort_unless($actor->hasAnyRole(['supervisor', 'sales_manager', 'owner', 'company_admin']), 403);
        abort_unless($salesman->user?->is_active, 422);

        if ($actor->hasAnyRole(['supervisor'])) {
            abort_unless(
                $actor->supervisor && SalesmanAssignment::query()
                    ->where('salesman_id', $salesman->id)
                    ->where('supervisor_id', $actor->supervisor->id)
                    ->current(now($actor->tenant->timezone)->toDateString())
                    ->exists(),
                403,
            );
        }

        $this->notifications->notifySafely(
            $salesman->user,
            'team.supervisor_nudge',
            'team_messages',
            'Message from your supervisor',
            $message,
            ['sender_name' => $actor->name, 'salesman_id' => $salesman->uuid],
            'high',
        );
    }

    private function alreadySent(User $user, string $key): bool
    {
        return OperationalNotification::query()
            ->where('user_id', $user->id)
            ->where('type', 'route.missed_visits')
            ->where('data->dedupe_key', $key)
            ->exists();
    }
}
