<?php

namespace App\Services;

use App\Models\Collection as CustomerCollection;
use App\Models\CurrentLocation;
use App\Models\CustomerVisit;
use App\Models\Order;
use App\Models\OperationalNotification;
use App\Models\Salesman;
use App\Models\SalesmanAssignment;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkSession;
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

        $sent += $this->sendIdleAlerts($tenant);

        return $sent;
    }

    public function sendIdleAlerts(Tenant $tenant): int
    {
        $settings = app(TrackingSettingsService::class)->get($tenant);

        if (! $settings['idle_alerts_enabled']) {
            return 0;
        }

        $timezone = $settings['timezone'];
        $now = CarbonImmutable::now($timezone);
        $start = CarbonImmutable::parse($now->toDateString().' '.$settings['workday_start_time'], $timezone);
        $end = CarbonImmutable::parse($now->toDateString().' '.$settings['workday_end_time'], $timezone);

        if ($now->lt($start) || $now->gt($end)) {
            return 0;
        }

        $threshold = (int) $settings['idle_alert_after_minutes'];
        $repeat = (int) $settings['idle_alert_repeat_minutes'];
        $sent = 0;

        $sessions = WorkSession::with(['salesman.user'])
            ->where('status', 'active')
            ->whereDate('date', $now->toDateString())
            ->get();

        $locations = CurrentLocation::query()
            ->whereIn('salesman_id', $sessions->pluck('salesman_id'))
            ->get()
            ->keyBy('salesman_id');

        foreach ($sessions as $session) {
            $user = $session->salesman?->user;
            $location = $locations->get($session->salesman_id);
            $activityCandidates = collect([
                $location?->recorded_at,
                CustomerVisit::query()->where('salesman_id', $session->salesman_id)->max('updated_at'),
                Order::query()->where('salesman_id', $session->salesman_id)->max('updated_at'),
                CustomerCollection::query()->where('salesman_id', $session->salesman_id)->max('updated_at'),
                $session->start_time,
            ])->filter()->map(fn ($value) => CarbonImmutable::parse($value));

            $lastActivity = $activityCandidates->sortDesc()->first();

            if (! $user?->is_active || ! $lastActivity) {
                continue;
            }

            $idleMinutes = CarbonImmutable::parse($lastActivity)->diffInMinutes(now());

            if ($idleMinutes < $threshold) {
                continue;
            }

            $recent = OperationalNotification::query()
                ->where('user_id', $user->id)
                ->where('type', 'team.idle_alert')
                ->where('created_at', '>=', now()->subMinutes($repeat))
                ->exists();

            if ($recent) {
                continue;
            }

            $this->notifications->notifySafely(
                $user,
                'team.idle_alert',
                'route_alerts',
                'Field activity reminder',
                "No recent field activity has been received for about {$idleMinutes} minutes. Please continue your route or update your status.",
                [
                    'idle_minutes' => $idleMinutes,
                    'last_activity_at' => $lastActivity->toISOString(),
                    'screen' => 'smart_route',
                ],
                'high',
            );
            $sent++;

            if (
                $settings['idle_escalation_enabled']
                && $idleMinutes >= $threshold + (int) $settings['idle_escalate_after_minutes']
            ) {
                $assignment = SalesmanAssignment::with('supervisor.user')
                    ->where('salesman_id', $session->salesman_id)
                    ->current($now->toDateString())
                    ->latest('effective_from')
                    ->first();
                $supervisor = $assignment?->supervisor?->user;

                if ($supervisor?->is_active) {
                    $escalationKey = "idle-escalation:{$now->toDateString()}:{$session->salesman_id}:".intdiv($idleMinutes, max(1, $repeat));

                    if (!OperationalNotification::query()
                        ->where('user_id', $supervisor->id)
                        ->where('type', 'team.idle_escalation')
                        ->where('data->dedupe_key', $escalationKey)
                        ->exists()) {
                        $this->notifications->notifySafely(
                            $supervisor,
                            'team.idle_escalation',
                            'route_alerts',
                            'Salesman still idle',
                            ($session->salesman?->full_name ?? 'A salesman')
                                ." has had no meaningful field activity for about {$idleMinutes} minutes.",
                            [
                                'dedupe_key' => $escalationKey,
                                'salesman_id' => $session->salesman?->uuid,
                                'idle_minutes' => $idleMinutes,
                                'screen' => 'team',
                            ],
                            'high',
                        );
                        $sent++;
                    }
                }
            }
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
