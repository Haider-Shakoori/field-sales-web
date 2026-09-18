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
    public function __construct(private readonly TenantContext $context)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        try {
            $plainTextToken = $request->bearerToken();

            if ($plainTextToken) {
                $user = $this->context->withAuthenticationBootstrapScope(function () use ($plainTextToken) {
                    $accessToken = PersonalAccessToken::findToken($plainTextToken);
                    $tokenable = $accessToken?->tokenable;

                    return $tokenable instanceof User ? $tokenable : null;
                });

                if ($user) {
                    $this->context->initializeTenant((int) $user->tenant_id);
                }
            }

            return $next($request);
        } finally {
            $this->context->clear();
        }
    }
}
