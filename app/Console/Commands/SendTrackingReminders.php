<?php

namespace App\Console\Commands;

use App\Models\CurrentLocation;
use App\Models\NotificationLog;
use App\Models\Salesman;
use App\Models\Tenant;
use App\Models\WorkSession;
use App\Services\NotificationService;
use App\Services\TenantClock;
use App\Services\TrackingSettingsService;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class SendTrackingReminders extends Command
{
    protected $signature = 'field-sales:tracking-reminders';

    protected $description = 'Send configured work-session/GPS reminders to salesmen who need attention.';

    public function handle(
        TrackingSettingsService $settingsService,
        TenantClock $clock,
        NotificationService $notifications,
        TenantContext $context,
    ): int {
        Tenant::where('subscription_status', 'active')
            ->chunkById(100, function ($tenants) use ($settingsService, $clock, $notifications, $context): void {
                foreach ($tenants as $tenant) {
                    $context->withTenant(
                        $tenant,
                        fn () => $this->processTenant($tenant, $settingsService, $clock, $notifications)
                    );
                }
            });

        return self::SUCCESS;
    }

    private function processTenant(
        Tenant $tenant,
        TrackingSettingsService $settingsService,
        TenantClock $clock,
        NotificationService $notifications,
    ): void {
        $settings = $settingsService->get($tenant);

        if (! $settings['tracking_auto_reminder_enabled']) {
            return;
        }

        $nowLocal = $clock->now($tenant);
        $date = $nowLocal->toDateString();

        if (! $this->insideWorkWindow($nowLocal, $settings['workday_start_time'], $settings['workday_end_time'])) {
            return;
        }

        $start = CarbonImmutable::parse(
            $date.' '.$settings['workday_start_time'],
            $settings['timezone']
        );

        if ($this->isOvernight($settings['workday_start_time'], $settings['workday_end_time'])
            && $nowLocal->format('H:i') < $settings['workday_end_time']) {
            $start = $start->subDay();
            $date = $start->toDateString();
        }

        if ($nowLocal->lt($start->addMinutes($settings['tracking_reminder_delay_minutes']))) {
            return;
        }

        $salesmen = Salesman::with('user')
            ->where('is_active', true)
            ->get();

        foreach ($salesmen as $salesman) {
            if (! $salesman->user?->is_active) {
                continue;
            }

            $todayCount = NotificationLog::where('recipient_user_id', $salesman->user_id)
                ->where('type', 'tracking_reminder')
                ->whereDate('created_at', now()->toDateString())
                ->count();

            if ($todayCount >= $settings['tracking_reminder_daily_limit']) {
                continue;
            }

            $last = NotificationLog::where('recipient_user_id', $salesman->user_id)
                ->where('type', 'tracking_reminder')
                ->latest()
                ->first();

            if ($last && $last->created_at->gt(now()->subMinutes($settings['tracking_reminder_repeat_minutes']))) {
                continue;
            }

            $session = WorkSession::where('salesman_id', $salesman->id)
                ->whereDate('date', $date)
                ->latest('start_time')
                ->first();

            if (! $session) {
                $notifications->send(
                    $salesman->user,
                    'tracking_reminder',
                    'Start your work day',
                    'Your scheduled work period has started. Please open Field Sales and start or restore tracking.',
                    ['reason' => 'no_work_session']
                );
                continue;
            }

            if ($session->status !== 'active' || ! $settings['gps_tracking_enabled']) {
                continue;
            }

            $fresh = CurrentLocation::where('user_id', $salesman->user_id)
                ->where('recorded_at', '>=', now()->subMinutes($settings['gps_stale_after_minutes']))
                ->exists();

            if (! $fresh) {
                $notifications->send(
                    $salesman->user,
                    'tracking_reminder',
                    'Tracking appears offline',
                    'Please open Field Sales and check location services so work tracking can resume.',
                    ['reason' => 'gps_stale']
                );
            }
        }
    }

    private function insideWorkWindow(CarbonImmutable $now, string $start, string $end): bool
    {
        $current = $now->format('H:i');

        if (! $this->isOvernight($start, $end)) {
            return $current >= $start && $current < $end;
        }

        return $current >= $start || $current < $end;
    }

    private function isOvernight(string $start, string $end): bool
    {
        return $end < $start;
    }
}
