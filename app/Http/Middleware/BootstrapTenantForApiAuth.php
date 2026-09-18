<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class BootstrapTenantForApiAuth
{
    public function __construct(
        private readonly TenantContext $context,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        try {
            $user = $this->context->withAuthenticationBootstrapScope(
                fn () => $request->user()
            );

            if (! $user && $request->bearerToken()) {
                $plainTextToken = $request->bearerToken();

                $user = $this->context->withAuthenticationBootstrapScope(function () use ($plainTextToken) {
                    $accessToken = PersonalAccessToken::findToken($plainTextToken);
                    $tokenable = $accessToken?->tokenable;

                    return $tokenable instanceof User ? $tokenable : null;
                });
            }

            if ($user instanceof User) {
                $this->context->initializeTenant((int) $user->tenant_id);
            }

            return $next($request);
        } finally {
            $this->context->clear();
        }
    }
}
