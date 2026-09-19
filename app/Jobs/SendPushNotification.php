<?php

namespace App\Jobs;

use App\Models\NotificationDelivery;
use App\Services\PushNotificationService;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendPushNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $deliveryId,
    ) {}

    public function handle(
        TenantContext $context,
        PushNotificationService $push,
    ): void {
        $context->withTenant($this->tenantId, function () use ($push): void {
            $delivery = NotificationDelivery::find($this->deliveryId);

            if (! $delivery || in_array($delivery->status, ['sent', 'skipped'], true)) {
                return;
            }

            $push->deliver($delivery);
        });
    }
}
