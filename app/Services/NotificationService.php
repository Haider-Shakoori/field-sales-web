<?php

namespace App\Services;

use App\Jobs\SendPushNotification;
use App\Models\Device;
use App\Models\NotificationDelivery;
use App\Models\NotificationPreference;
use App\Models\OperationalNotification;
use App\Models\User;

class NotificationService
{
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

        if (! $preferences->database_enabled || ! $this->categoryEnabled($preferences, $category)) {
            return null;
        }

        $notification = OperationalNotification::create([
            'user_id' => $recipient->id,
            'type' => $type,
            'category' => $category,
            'priority' => $priority,
            'title' => $title,
            'message' => $message,
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
