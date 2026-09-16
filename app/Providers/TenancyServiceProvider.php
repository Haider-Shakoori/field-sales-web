<?php

namespace App\Providers;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\CustomerCategory;
use App\Models\CustomerLocationHistory;
use App\Models\Device;
use App\Models\Role;
use App\Models\Route;
use App\Models\RouteCustomer;
use App\Models\Salesman;
use App\Models\SalesmanAssignment;
use App\Models\Supervisor;
use App\Models\SupervisorAssignment;
use App\Models\Tenant;
use App\Models\Territory;
use App\Models\User;
use App\Policies\AuditLogPolicy;
use App\Policies\BranchPolicy;
use App\Policies\CompanySettingPolicy;
use App\Policies\CustomerCategoryPolicy;
use App\Policies\CustomerLocationHistoryPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\DevicePolicy;
use App\Policies\RolePolicy;
use App\Policies\RouteCustomerPolicy;
use App\Policies\RoutePolicy;
use App\Policies\SalesmanAssignmentPolicy;
use App\Policies\SalesmanPolicy;
use App\Policies\SupervisorAssignmentPolicy;
use App\Policies\SupervisorPolicy;
use App\Policies\TenantPolicy;
use App\Policies\TerritoryPolicy;
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
        Customer::class => CustomerPolicy::class,
        CustomerCategory::class => CustomerCategoryPolicy::class,
        Territory::class => TerritoryPolicy::class,
        Route::class => RoutePolicy::class,
        RouteCustomer::class => RouteCustomerPolicy::class,
        CustomerLocationHistory::class => CustomerLocationHistoryPolicy::class,
        SalesmanAssignment::class => SalesmanAssignmentPolicy::class,
        SupervisorAssignment::class => SupervisorAssignmentPolicy::class,
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
