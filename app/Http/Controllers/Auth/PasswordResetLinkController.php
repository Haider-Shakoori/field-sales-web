<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Password reset link dispatch is an identity-resolution operation that
     * must locate users across all tenants.  Runs in an explicit system scope.
     */
    public function store(Request $request): RedirectResponse
    {
        return TenantContext::withSystemScope(function () use ($request): RedirectResponse {
            $request->validate([
                'email' => ['required', 'email'],
            ]);

            $status = Password::sendResetLink($request->only('email'));

            return $status === Password::RESET_LINK_SENT
                ? back()->with('status', __($status))
                : back()->withErrors(['email' => __($status)])->onlyInput('email');
        });
    }
}
