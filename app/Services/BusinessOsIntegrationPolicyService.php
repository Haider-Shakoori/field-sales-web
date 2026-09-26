<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;

final class BusinessOsIntegrationPolicyService
{
    public function settingsFor(Tenant $tenant): array
    {
        $settings = $tenant->settings ?? [];
        $available = $this->platformAvailable();
        $requested = (bool) data_get($settings, 'businessos.enabled', false);

        return [
            'platform_available' => $available,
            'enabled' => $available && $requested,
            'requested_enabled' => $requested,
            'organization_key' => trim((string) data_get(
                $settings,
                'businessos.organization_key',
                '',
            )),
            'pull_products' => (bool) data_get(
                $settings,
                'businessos.sync.pull_products',
                true,
            ),
            'pull_customers' => (bool) data_get(
                $settings,
                'businessos.sync.pull_customers',
                true,
            ),
            'pull_prices' => (bool) data_get(
                $settings,
                'businessos.sync.pull_prices',
                true,
            ),
            'push_orders' => (bool) data_get(
                $settings,
                'businessos.sync.push_orders',
                true,
            ),
            'push_collections' => (bool) data_get(
                $settings,
                'businessos.sync.push_collections',
                true,
            ),
            'push_field_customers' => (bool) data_get(
                $settings,
                'businessos.sync.push_field_customers',
                true,
            ),
            'sync_interval_minutes' => $this->interval(
                data_get($settings, 'businessos.sync.interval_minutes', 15),
            ),
            'base_url' => $this->safeBaseUrl(),
        ];
    }

    public function enabled(User|Tenant $subject): bool
    {
        return $this->settingsFor($this->tenant($subject))['enabled'];
    }

    public function platformAvailable(): bool
    {
        return (bool) config('businessos.enabled', false)
            && filled(config('businessos.base_url'))
            && filled(config('businessos.token'));
    }

    private function tenant(User|Tenant $subject): Tenant
    {
        if ($subject instanceof Tenant) {
            return $subject;
        }

        return $subject->loadMissing('tenant')->tenant;
    }

    private function interval(mixed $value): int
    {
        $allowed = [5, 15, 30, 60, 120, 240];
        $value = (int) $value;

        return in_array($value, $allowed, true) ? $value : 15;
    }

    private function safeBaseUrl(): ?string
    {
        $value = trim((string) config('businessos.base_url'));

        return $value === '' ? null : rtrim($value, '/');
    }
}
