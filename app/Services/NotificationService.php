<?php

namespace App\Services;

use App\Models\Device;
use App\Models\NotificationLog;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class NotificationService
{
    public function send(User $recipient, string $type, string $title, string $body, array $data = [], ?User $actor = null): NotificationLog
    {
        $log = NotificationLog::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $recipient->tenant_id,
            'recipient_user_id' => $recipient->id,
            'actor_user_id' => $actor?->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data,
            'status' => 'queued',
        ]);

        $tokens = Device::where('tenant_id', $recipient->tenant_id)
            ->where('user_id', $recipient->id)
            ->where('is_active', true)
            ->whereNull('revoked_at')
            ->whereNotNull('push_token')
            ->pluck('push_token')
            ->filter()
            ->values();

        if ($tokens->isEmpty()) {
            $log->update(['status' => 'queued_no_push_token']);
            return $log;
        }

        $endpoint = config('services.field_sales_push.endpoint');
        $secret = config('services.field_sales_push.secret');

        if (! $endpoint) {
            $log->update(['status' => 'queued_no_provider']);
            return $log;
        }

        try {
            $response = Http::timeout(10)
                ->when($secret, fn ($request) => $request->withToken($secret))
                ->post($endpoint, [
                    'tokens' => $tokens,
                    'notification' => ['title' => $title, 'body' => $body],
                    'data' => array_merge($data, ['type' => $type, 'notification_uuid' => $log->uuid]),
                ]);

            if ($response->successful()) {
                $log->update(['status' => 'sent', 'sent_at' => now()]);
            } else {
                $log->update(['status' => 'failed', 'error' => 'Push provider returned HTTP '.$response->status()]);
            }
        } catch (\Throwable $exception) {
            $log->update(['status' => 'failed', 'error' => $exception->getMessage()]);
        }

        return $log->fresh();
    }
}
