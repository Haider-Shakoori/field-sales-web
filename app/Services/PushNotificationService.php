<?php

namespace App\Services;

use App\Models\NotificationDelivery;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
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

        $token = trim((string) $delivery->device?->push_token);

        if ($token === '') {
            return $this->finish($delivery, 'skipped', 'Device push token is missing.');
        }

        try {
            return config('push.provider') === 'fcm_v1'
                ? $this->deliverFcm($delivery, $token)
                : $this->deliverGeneric($delivery, $token);
        } catch (Throwable $exception) {
            return $this->finish($delivery, 'failed', $exception->getMessage());
        }
    }

    private function deliverFcm(NotificationDelivery $delivery, string $token): NotificationDelivery
    {
        $credentials = $this->fcmCredentials();
        $projectId = trim((string) config('push.fcm.project_id'));

        if ($projectId === '') {
            $projectId = (string) ($credentials['project_id'] ?? '');
        }

        if ($projectId === '') {
            throw new RuntimeException('FCM project ID is missing.');
        }

        $data = collect($delivery->notification->data ?? [])
            ->mapWithKeys(fn ($value, $key) => [(string) $key => is_scalar($value) || $value === null
                ? (string) $value
                : json_encode($value, JSON_UNESCAPED_SLASHES)])
            ->all();

        $response = Http::timeout((int) config('push.timeout_seconds', 10))
            ->withToken($this->fcmAccessToken($credentials))
            ->acceptJson()
            ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                'message' => [
                    'token' => $token,
                    'notification' => [
                        'title' => $delivery->notification->title,
                        'body' => $delivery->notification->message,
                    ],
                    'data' => $data,
                    'android' => [
                        'priority' => $delivery->notification->priority === 'high' ? 'HIGH' : 'NORMAL',
                        'notification' => [
                            'channel_id' => 'fieldpulse_operational',
                            'sound' => 'default',
                        ],
                    ],
                ],
            ]);

        if ($response->successful()) {
            return $this->finish($delivery, 'sent');
        }

        return $this->finish($delivery, 'failed', 'FCM returned HTTP '.$response->status().'.');
    }

    private function deliverGeneric(NotificationDelivery $delivery, string $token): NotificationDelivery
    {
        $endpoint = trim((string) config('push.endpoint'));
        $bearer = trim((string) config('push.bearer_token'));

        if ($endpoint === '' || $bearer === '') {
            return $this->finish($delivery, 'skipped', 'Generic push provider configuration is missing.');
        }

        $response = Http::timeout((int) config('push.timeout_seconds', 10))
            ->withToken($bearer)
            ->acceptJson()
            ->post($endpoint, [
                'token' => $token,
                'title' => $delivery->notification->title,
                'body' => $delivery->notification->message,
                'data' => $delivery->notification->data ?? [],
            ]);

        return $response->successful()
            ? $this->finish($delivery, 'sent')
            : $this->finish($delivery, 'failed', 'Push provider returned HTTP '.$response->status().'.');
    }

    private function fcmCredentials(): array
    {
        $json = trim((string) config('push.fcm.service_account_json'));
        $path = trim((string) config('push.fcm.service_account_path'));

        if ($json === '' && $path !== '' && is_readable($path)) {
            $json = (string) file_get_contents($path);
        }

        $credentials = json_decode($json, true);

        if (! is_array($credentials) || empty($credentials['client_email']) || empty($credentials['private_key'])) {
            throw new RuntimeException('Valid FCM service-account credentials are missing.');
        }

        return $credentials;
    }

    private function fcmAccessToken(array $credentials): string
    {
        $cacheKey = 'fieldpulse:fcm-access-token:'.sha1((string) $credentials['client_email']);

        return Cache::remember($cacheKey, now()->addMinutes(50), function () use ($credentials): string {
            $now = time();
            $header = $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $claims = $this->base64Url(json_encode([
                'iss' => $credentials['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ]));
            $unsigned = $header.'.'.$claims;

            if (! openssl_sign($unsigned, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256)) {
                throw new RuntimeException('Could not sign FCM service-account assertion.');
            }

            $response = Http::asForm()
                ->timeout((int) config('push.timeout_seconds', 10))
                ->post('https://oauth2.googleapis.com/token', [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $unsigned.'.'.$this->base64Url($signature),
                ]);

            $token = $response->json('access_token');

            if (! $response->successful() || ! is_string($token) || $token === '') {
                throw new RuntimeException('Could not obtain an FCM OAuth access token.');
            }

            return $token;
        });
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function finish(NotificationDelivery $delivery, string $status, ?string $error = null): NotificationDelivery
    {
        $delivery->forceFill(['status' => $status, 'last_error' => $error])->save();

        return $delivery;
    }
}
