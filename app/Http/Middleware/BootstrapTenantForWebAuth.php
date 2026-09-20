<?php

namespace App\Http\Middleware;

use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BootstrapTenantForWebAuth
{
    /**
     * Platform console routes run without tenant scoping so platform
     * administrators can operate across organizations.
     */
    private const PLATFORM_ROUTES = [
        'admin.organizations.*',
    ];

    public function __construct(
        private readonly TenantContext $context,
        private readonly AuthFactory $auth,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        try {
            $guard = $this->auth->guard();

            $user = $this->context->withAuthenticationBootstrapScope(
                fn () => $guard->user()
            );

            if ($user) {
                if ($user->isPlatformAdmin() && $request->routeIs(...self::PLATFORM_ROUTES)) {
                    $this->context->initializePlatform();
                } else {
                    $this->context->initializeTenant((int) $user->tenant_id);
                }
            } elseif ($request->hasSession()) {
                $request->session()->forget($guard->getName());
                $guard->forgetUser();
            }

            return $next($request);
        } finally {
            $this->context->clear();
        }
    }
}
