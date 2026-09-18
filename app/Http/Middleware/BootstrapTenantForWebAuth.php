<?php

namespace App\Http\Middleware;

use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BootstrapTenantForWebAuth
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly AuthFactory $auth,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        try {
            $guard = $this->auth->guard();

            $user = $this->context->withAuthenticationBootstrapScope(
                fn () => $guard->user()
            );

            if ($user) {
                $this->context->initializeTenant((int) $user->tenant_id);
            } elseif ($request->hasSession()) {
                // If a stale session id remains, remove it so later requests do
                // not repeatedly attempt an identity lookup in bootstrap scope.
                $request->session()->forget($guard->getName());
                $guard->forgetUser();
            }

            return $next($request);
        } finally {
            $this->context->clear();
        }
    }
}
