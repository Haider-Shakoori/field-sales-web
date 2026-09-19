<?php

namespace App\Services;

use App\Jobs\SendPushNotification;
use App\Models\Device;
use App\Models\NotificationDelivery;
use App\Models\NotificationPreference;
use App\Models\SalesmanAssignment;
use App\Models\OperationalNotification;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class NotificationService
{
    public function __construct(private readonly TenantClock $clock) {}

    public function preferences(User $user): NotificationPreference
    {
        return NotificationPreference::firstOrCreate(
            ['user_id' => $user->id],
            [
                'database_enabled' => true,
                'push_enabled' => true,
                'order_updates' => true,
                'collection_updates' => true,
                'expense_updates' => true,
                'suspicious_alerts' => true,
            ],
        );
    }

    public function notify(
        User $recipient,
        string $type,
        string $category,
        string $title,
        string $message,
        array $data = [],
        string $priority = 'normal',
    ): ?OperationalNotification {
        $preferences = $this->preferences($recipient);

        if (! $this->categoryEnabled($preferences, $category)) {
            return null;
        }

        $notification = OperationalNotification::create([
            'user_id' => $recipient->id,
            'type' => $type,
            'category' => $category,
            'priority' => $priority,
            'title' => $title,
            'message' => $message,
            'database_visible' => $preferences->database_enabled,
            'data' => $data ?: null,
        ]);

        if (! $preferences->push_enabled) {
            return $notification;
        }

        $device = Device::query()
            ->where('user_id', $recipient->id)
            ->where('is_active', true)
            ->whereNull('revoked_at')
            ->whereNotNull('push_token')
            ->latest('last_seen_at')
            ->first();

        if (! $device) {
            return $notification;
        }

        $delivery = NotificationDelivery::create([
            'notification_id' => $notification->id,
            'device_id' => $device->id,
            'channel' => 'push',
            'provider' => 'generic_http',
            'status' => config('push.enabled') ? 'pending' : 'skipped',
            'last_error' => config('push.enabled')
                ? null
                : 'Push delivery is disabled.',
        ]);

        if (config('push.enabled')) {
            SendPushNotification::dispatch(
                (int) $recipient->tenant_id,
                (int) $delivery->id,
            )->afterCommit();
        }

        return $notification;
    }

    public function notifySuspiciousVisit(\App\Models\VisitSuspiciousFlag $flag): void
    {
        $flag->loadMissing(['tenant', 'visit.salesman']);
        $visit = $flag->visit;

        if (! $visit) {
            return;
        }

        $timezone = $this->clock->timezone($flag->tenant);
        $localDate = $visit->checked_in_at
            ? $visit->checked_in_at->setTimezone($timezone)->toDateString()
            : $this->clock->now($flag->tenant)->toDateString();

        $managerUserIds = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.tenant_id', $flag->tenant_id)
            ->where('roles.tenant_id', $flag->tenant_id)
            ->whereIn('roles.slug', [
                'owner',
                'company_admin',
                'sales_manager',
            ])
            ->pluck('model_has_roles.user_id');

        $recipients = User::query()
            ->whereIn('id', $managerUserIds)
            ->where('is_active', true)
            ->get();

        $assignment = SalesmanAssignment::with('supervisor.user')
            ->where('salesman_id', $visit->salesman_id)
            ->current($localDate)
            ->latest('effective_from')
            ->first();

        if ($assignment?->supervisor?->user?->is_active) {
            $recipients->push($assignment->supervisor->user);
        }

        $recipients
            ->unique('id')
            ->each(function (User $recipient) use ($flag, $visit): void {
                $this->notify(
                    $recipient,
                    'visit.suspicious_flag',
                    'suspicious_alerts',
                    'Suspicious visit evidence detected',
                    ($visit->salesman?->full_name ?? 'A salesman')
                        .' triggered '.str($flag->reason_code)->replace('_', ' ').'.',
                    [
                        'visit_id' => $visit->uuid,
                        'flag_id' => $flag->uuid,
                        'reason' => $flag->reason_code,
                        'severity' => $flag->severity,
                    ],
                    $flag->severity === 'high' ? 'high' : 'normal',
                );
            });
    }

    private function categoryEnabled(
        NotificationPreference $preferences,
        string $category,
    ): bool {
        return match ($category) {
            'order_updates' => $preferences->order_updates,
            'collection_updates' => $preferences->collection_updates,
            'expense_updates' => $preferences->expense_updates,
            'suspicious_alerts' => $preferences->suspicious_alerts,
            default => true,
        };
    }
}
