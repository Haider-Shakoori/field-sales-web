<?php

namespace App\Providers;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\CompanySetting;
use App\Models\Device;
use App\Models\Role;
use App\Models\Salesman;
use App\Models\Supervisor;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\AuditLogPolicy;
use App\Policies\BranchPolicy;
use App\Policies\CompanySettingPolicy;
use App\Policies\DevicePolicy;
use App\Policies\RolePolicy;
use App\Policies\SalesmanPolicy;
use App\Policies\SupervisorPolicy;
use App\Policies\TenantPolicy;
use App\Policies\UserPolicy;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider;
use Illuminate\Support\Facades\Gate;

class TenancyServiceProvider extends AuthServiceProvider
{
    /**
     * The policy-map for this application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Tenant::class => TenantPolicy::class,
        Branch::class => BranchPolicy::class,
        User::class => UserPolicy::class,
        CompanySetting::class => CompanySettingPolicy::class,
        Role::class => RolePolicy::class,
        AuditLog::class => AuditLogPolicy::class,
        Salesman::class => SalesmanPolicy::class,
        Supervisor::class => SupervisorPolicy::class,
        Device::class => DevicePolicy::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantContext::class, fn () => new TenantContext);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        Gate::before(function (User $user, string $ability): ?bool {
            if ($user->isSuperAdmin()) {
                return true;
            }

            if (str_contains($ability, ':')) {
                return $user->hasPermission($ability);
            }

            return null;
        });
    }
}
