<?php

namespace App\Http\Middleware;

use App\Support\Http\ApiResponse;
use App\Support\Mobile\MobileSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceMinimumAppVersion
{
    /**
     * Enforce the configured minimum app version.  Returns 426 with upgrade
     * details when the request's X-App-Version header falls below the threshold.
     * Requests without the header pass through (builds, tooling, etc.).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $appVersion = $request->header('X-App-Version');

        if ($appVersion === null) {
            return $next($request);
        }

        if (! MobileSettings::appVersionSatisfies($appVersion)) {
            return ApiResponse::error('App version not supported. Please upgrade.', 426, [
                'current_version' => $appVersion,
                'required_version' => MobileSettings::minAppVersion(),
                'upgrade_url' => MobileSettings::upgradeUrl(),
            ]);
        }

        return $next($request);
    }
}
