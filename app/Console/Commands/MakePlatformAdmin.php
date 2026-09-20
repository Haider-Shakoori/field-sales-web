<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;

class MakePlatformAdmin extends Command
{
    protected $signature = 'field-sales:make-platform-admin
        {email : Email of the user to promote or demote}
        {--tenant= : Tenant slug or UUID when the email exists in multiple organizations}
        {--revoke : Remove platform administrator access instead}';

    protected $description = 'Grant or revoke platform administrator access for an organization user.';

    public function handle(TenantContext $context): int
    {
        $email = (string) $this->argument('email');
        $tenant = $this->option('tenant');

        $matches = $context->withAuthenticationBootstrapScope(function () use ($email, $tenant) {
            return User::with('tenant')
                ->where('email', $email)
                ->when(
                    $tenant,
                    fn ($query, $identifier) => $query->whereHas(
                        'tenant',
                        fn ($tenantQuery) => $tenantQuery
                            ->where('uuid', $identifier)
                            ->orWhere('slug', $identifier)
                    )
                )
                ->limit(2)
                ->get();
        });

        if ($matches->isEmpty()) {
            $this->error('No user found for '.$email.'.');

            return self::FAILURE;
        }

        if ($matches->count() > 1) {
            $this->error('This email exists in multiple organizations. Pass --tenant=<slug|uuid>.');

            return self::FAILURE;
        }

        /** @var User $user */
        $user = $matches->first();

        $grant = ! $this->option('revoke');

        app(TenantContext::class)->withTenant($user->tenant, function () use ($user, $grant): void {
            $user->forceFill(['is_platform_admin' => $grant])->save();
        });

        $organization = $user->tenant instanceof Tenant ? $user->tenant->name : 'their organization';

        $this->info(($grant ? 'Granted' : 'Revoked').' platform administrator access for '.$user->email.' ('.$organization.').');

        return self::SUCCESS;
    }
}
