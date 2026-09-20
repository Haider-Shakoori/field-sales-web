<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function create()
    {
        return view('auth.login');
    }

    public function store(Request $request, TenantContext $context)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'tenant' => ['nullable', 'string', 'max:191'],
        ]);

        $matches = $context->withAuthenticationBootstrapScope(function () use ($credentials) {
            return User::with('tenant')
                ->where('email', $credentials['email'])
                ->when(
                    $credentials['tenant'] ?? null,
                    fn ($query, $tenant) => $query->whereHas(
                        'tenant',
                        fn ($tenantQuery) => $tenantQuery
                            ->where('uuid', $tenant)
                            ->orWhere('slug', $tenant)
                    )
                )
                ->limit(2)
                ->get();
        });

        if ($matches->count() > 1 && empty($credentials['tenant'])) {
            return back()
                ->withErrors(['email' => 'This email belongs to more than one company. Enter the company identifier.'])
                ->onlyInput('email', 'tenant');
        }

        /** @var User|null $user */
        $user = $matches->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password) || ! $user->is_active) {
            return back()
                ->withErrors(['email' => 'Invalid credentials.'])
                ->onlyInput('email', 'tenant');
        }

        if ($user->tenant?->subscription_status !== 'active') {
            return back()
                ->withErrors(['email' => 'This company account is suspended. Contact the platform administrator.'])
                ->onlyInput('email', 'tenant');
        }

        $context->initializeTenant((int) $user->tenant_id);

        $webPermissions = [
            'settings:view',
            'users:view',
            'roles:view',
            'branches:view',
            'audit:view',
            'reports:view',
            'tracking:view',
        ];

        if (! $user->hasAnyPermission($webPermissions)) {
            return back()
                ->withErrors(['email' => 'Administrator access required.'])
                ->onlyInput('email', 'tenant');
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->intended($this->landingPage($user));
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }

    private function landingPage(User $user): string
    {
        return match (true) {
            $user->hasPermission('reports:view') => route('admin.dashboard'),
            $user->hasPermission('users:view') => route('admin.users.index'),
            $user->hasPermission('roles:view') => route('admin.roles.index'),
            $user->hasPermission('branches:view') => route('admin.branches.index'),
            $user->hasPermission('settings:view') => route('tracking.edit'),
            default => route('admin.audit.index'),
        };
    }
}
