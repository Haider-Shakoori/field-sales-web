<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function show(): View
    {
        return view('auth.login');
    }

    /**
     * Authentication is an identity-resolution operation, not a tenant-scoped
     * business action.  The query that resolves the user by email/password
     * crosses tenant boundaries intentionally and must run outside the tenant
     * scope.
     */
    public function store(Request $request): RedirectResponse
    {
        return TenantContext::withSystemScope(function () use ($request): RedirectResponse {
            $credentials = $request->validate([
                'email' => ['required', 'email'],
                'password' => ['required'],
            ]);

            $throttleKey = 'login:'.$request->ip();

            if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
                throw ValidationException::withMessages([
                    'email' => __('auth.throttle', [
                        'seconds' => RateLimiter::availableIn($throttleKey),
                    ]),
                ]);
            }

            if (! Auth::attempt($credentials, $request->boolean('remember'))) {
                RateLimiter::hit($throttleKey, 60);

                throw ValidationException::withMessages([
                    'email' => __('auth.failed'),
                ]);
            }

            RateLimiter::clear($throttleKey);
            $request->session()->regenerate();

            $request->user()?->lastLogin();

            return redirect()->intended(route('dashboard'));
        });
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
