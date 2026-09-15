<?php

namespace App\Support\Mobile;

use App\Models\CompanySetting;
use App\Support\Tenancy\TenantContext;

final class MobileSettings
{
    /**
     * Resolve a mobile setting: check tenant company_setting first, fall back
     * to config('tenancy.mobile.*'). Scoped to the current tenant context when active.
     */
    public static function resolve(string $key, mixed $default = null): mixed
    {
        $tenantId = TenantContext::hasContext() ? TenantContext::currentId() : null;

        if ($tenantId === null) {
            return config("tenancy.mobile.{$key}", $default);
        }

        return CompanySetting::query()
            ->where('key', "mobile.{$key}")
            ->value('value') ?? config("tenancy.mobile.{$key}", $default);
    }

    public static function minAppVersion(): ?string
    {
        return self::resolve('min_app_version', config('tenancy.mobile.min_app_version'));
    }

    public static function upgradeUrl(): ?string
    {
        return self::resolve('upgrade_url', config('tenancy.mobile.upgrade_url'));
    }

    public static function oneDevicePerSalesman(): bool
    {
        return filter_var(
            self::resolve('one_device_per_salesman', config('tenancy.mobile.one_device_per_salesman', true)),
            FILTER_VALIDATE_BOOLEAN,
        );
    }

    /**
     * Check if $currentVersion satisfies the minimum app version requirement.
     */
    public static function appVersionSatisfies(?string $currentVersion): bool
    {
        if ($currentVersion === null) {
            return true;
        }

        $min = self::minAppVersion();

        if ($min === null) {
            return true;
        }

        return version_compare($currentVersion, $min, '>=');
    }

    /**
     * The upgrade payload returned to clients running an outdated app version.
     *
     * @return array{current: ?string, required: ?string, upgrade_url: ?string}|null
     */
    public static function upgradePayload(?string $currentVersion): ?array
    {
        if (self::appVersionSatisfies($currentVersion)) {
            return null;
        }

        return [
            'current_version' => $currentVersion,
            'required_version' => self::minAppVersion(),
            'upgrade_url' => self::upgradeUrl(),
        ];
    }
}
