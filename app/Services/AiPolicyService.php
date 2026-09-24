<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;

class AiPolicyService
{
    public function externalEnabled(User|Tenant|null $subject = null): bool
    {
        if (! (bool) config('ai.enabled', false)) {
            return false;
        }

        $tenant = $this->tenant($subject);
        $override = data_get($tenant?->settings, 'ai.enabled');

        return $override === null ? true : (bool) $override;
    }

    public function customerDataEnabled(User $user): bool
    {
        if (! $this->externalEnabled($user)) {
            return false;
        }

        if (! (bool) config('ai.allow_customer_data', false)) {
            return false;
        }

        $tenant = $this->tenant($user);
        $override = data_get($tenant?->settings, 'ai.allow_customer_data');

        return $override === null ? true : (bool) $override;
    }

    public function historyRetentionDays(User|Tenant|null $subject = null): int
    {
        $tenant = $this->tenant($subject);
        $configured = data_get(
            $tenant?->settings,
            'ai.history_retention_days',
            config('ai.history_retention_days', 90),
        );
        $days = (int) $configured;

        if ($days === 0) {
            return 0;
        }

        return min(365, max(7, $days));
    }

    public function settingsFor(Tenant $tenant): array
    {
        return [
            'external_enabled' => $this->externalEnabled($tenant),
            'external_available' => (bool) config('ai.enabled', false),
            'customer_data_enabled' => (bool) config('ai.allow_customer_data', false)
                && (bool) data_get(
                    $tenant->settings,
                    'ai.allow_customer_data',
                    true,
                ),
            'customer_data_available' => (bool) config(
                'ai.allow_customer_data',
                false,
            ),
            'history_retention_days' => $this->historyRetentionDays($tenant),
        ];
    }

    private function tenant(User|Tenant|null $subject): ?Tenant
    {
        if ($subject instanceof Tenant) {
            return $subject;
        }

        if ($subject instanceof User) {
            return $subject->loadMissing('tenant')->tenant;
        }

        return null;
    }
}
