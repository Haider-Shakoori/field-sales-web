<?php

namespace App\Jobs;

use App\Models\CustomerCommunicationDelivery;
use App\Services\CustomerCommunicationService;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendCustomerCommunication implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $deliveryId,
    ) {}

    public function handle(
        TenantContext $context,
        CustomerCommunicationService $communications,
    ): void {
        $context->withTenant($this->tenantId, function () use ($communications): void {
            $delivery = CustomerCommunicationDelivery::find($this->deliveryId);

            if (! $delivery || in_array($delivery->status, ['sent', 'skipped'], true)) {
                return;
            }

            $communications->deliver($delivery);
        });
    }
}
