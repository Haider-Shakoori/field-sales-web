<?php

namespace App\Services;

use App\Models\NotificationDelivery;
use Illuminate\Support\Facades\Http;
use Throwable;

class PushNotificationService
{
    public function deliver(NotificationDelivery $delivery): NotificationDelivery
    {
        $delivery->loadMissing(['notification', 'device']);
        $delivery->increment('attempts');
        $delivery->forceFill(['last_attempted_at' => now()])->save();

        if (! config('push.enabled')) {
            return $this->finish($delivery, 'skipped', 'Push delivery is disabled.');
        }

        $endpoint = trim((string) config('push.endpoint'));
        $bearer = trim((string) config('push.bearer_token'));
        $token = trim((string) $delivery->device?->push_token);

        if ($endpoint === '' || $bearer === '' || $token === '') {
            return $this->finish(
                $delivery,
                'skipped',
                'Push provider configuration or device token is missing.',
            );
        }

        try {
            $response = Http::timeout((int) config('push.timeout_seconds', 10))
                ->withToken($bearer)
                ->acceptJson()
                ->post($endpoint, [
                    'token' => $token,
                    'title' => $delivery->notification->title,
                    'body' => $delivery->notification->message,
                    'data' => $delivery->notification->data ?? [],
                ]);

            if ($response->successful()) {
                return $this->finish($delivery, 'sent');
            }

            return $this->finish(
                $delivery,
                'failed',
                'Push provider returned HTTP '.$response->status().'.',
            );
        } catch (Throwable $exception) {
            return $this->finish($delivery, 'failed', $exception->getMessage());
        }
    }

    private function finish(
        NotificationDelivery $delivery,
        string $status,
        ?string $error = null,
    ): NotificationDelivery {
        $delivery->forceFill([
            'status' => $status,
            'last_error' => $error,
        ])->save();

        return $delivery;
    }
}
