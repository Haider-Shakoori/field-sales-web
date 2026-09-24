<?php

namespace App\Services;

use App\Jobs\SendCustomerCommunication;
use App\Models\Customer;
use App\Models\CustomerCommunicationDelivery;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Throwable;

class CustomerCommunicationService
{
    public const CHANNELS = ['whatsapp', 'sms'];

    public function queue(
        Customer $customer,
        User $actor,
        string $channel,
        string $message,
        string $kind = 'custom',
    ): CustomerCommunicationDelivery {
        if (! in_array($channel, self::CHANNELS, true)) {
            throw ValidationException::withMessages([
                'channel' => 'The selected communication channel is invalid.',
            ]);
        }

        $phone = $this->recipientPhone($customer);

        if ($phone === null) {
            throw ValidationException::withMessages([
                'phone' => 'This customer does not have a usable phone number.',
            ]);
        }

        $enabled = (bool) config("communications.{$channel}.enabled", false);
        $provider = trim((string) config("communications.{$channel}.provider", 'generic_http'));

        $delivery = CustomerCommunicationDelivery::create([
            'customer_id' => $customer->id,
            'created_by' => $actor->id,
            'channel' => $channel,
            'kind' => $kind,
            'recipient_phone' => $phone,
            'message' => trim($message),
            'provider' => $provider !== '' ? $provider : 'generic_http',
            'status' => $enabled ? 'pending' : 'skipped',
            'last_error' => $enabled
                ? null
                : ucfirst($channel).' delivery is disabled.',
        ]);

        if ($enabled) {
            SendCustomerCommunication::dispatch(
                (int) $customer->tenant_id,
                (int) $delivery->id,
            )->afterCommit();
        }

        return $delivery;
    }

    public function deliver(
        CustomerCommunicationDelivery $delivery,
    ): CustomerCommunicationDelivery {
        if (in_array($delivery->status, ['sent', 'skipped'], true)) {
            return $delivery;
        }

        $channel = $delivery->channel;

        if (! in_array($channel, self::CHANNELS, true)) {
            return $this->finish($delivery, 'failed', 'Unsupported communication channel.');
        }

        $delivery->increment('attempts');
        $delivery->forceFill(['last_attempted_at' => now()])->save();

        if (! config("communications.{$channel}.enabled", false)) {
            return $this->finish(
                $delivery,
                'skipped',
                ucfirst($channel).' delivery is disabled.',
            );
        }

        $endpoint = trim((string) config("communications.{$channel}.endpoint"));
        $bearer = trim((string) config("communications.{$channel}.bearer_token"));
        $timeout = (int) config("communications.{$channel}.timeout_seconds", 10);

        if ($endpoint === '') {
            return $this->finish(
                $delivery,
                'failed',
                ucfirst($channel).' provider endpoint is missing.',
            );
        }

        try {
            $request = Http::timeout(max(1, $timeout))->acceptJson();

            if ($bearer !== '') {
                $request = $request->withToken($bearer);
            }

            $response = $request->post($endpoint, [
                'channel' => $channel,
                'to' => $delivery->recipient_phone,
                'message' => $delivery->message,
                'reference' => $delivery->uuid,
                'kind' => $delivery->kind,
                'customer_id' => $delivery->customer?->uuid,
            ]);

            if (! $response->successful()) {
                return $this->finish(
                    $delivery,
                    'failed',
                    ucfirst($channel).' provider returned HTTP '.$response->status().'.',
                );
            }

            $providerMessageId = $response->json('message_id')
                ?? $response->json('id');

            return $this->finish(
                $delivery,
                'sent',
                null,
                is_scalar($providerMessageId) ? (string) $providerMessageId : null,
            );
        } catch (Throwable $exception) {
            return $this->finish($delivery, 'failed', $exception->getMessage());
        }
    }

    public function recipientPhone(Customer $customer): ?string
    {
        $raw = trim((string) ($customer->phone ?: $customer->alternate_phone));

        if ($raw === '') {
            return null;
        }

        $prefix = str_starts_with($raw, '+') ? '+' : '';
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        return $digits === '' ? null : $prefix.$digits;
    }

    private function finish(
        CustomerCommunicationDelivery $delivery,
        string $status,
        ?string $error = null,
        ?string $providerMessageId = null,
    ): CustomerCommunicationDelivery {
        $delivery->forceFill([
            'status' => $status,
            'last_error' => $error,
            'provider_message_id' => $providerMessageId,
            'sent_at' => $status === 'sent' ? now() : $delivery->sent_at,
        ])->save();

        return $delivery;
    }
}
