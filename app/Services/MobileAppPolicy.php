<?php

namespace App\Services;

class MobileAppPolicy
{
    public function minimumVersion(): string
    {
        return (string) config('tenancy.mobile.minimum_version', '1.0');
    }

    public function upgradeUrl(): ?string
    {
        $value = config('tenancy.mobile.upgrade_url');

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function isSupported(?string $version): bool
    {
        if ($version === null || trim($version) === '') {
            return true;
        }

        return version_compare($version, $this->minimumVersion(), '>=');
    }

    public function payload(?string $currentVersion): array
    {
        return [
            'current_version' => $currentVersion,
            'required_version' => $this->minimumVersion(),
            'upgrade_url' => $this->upgradeUrl(),
        ];
    }
}
