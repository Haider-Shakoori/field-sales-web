<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerPortalAccess;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\Territory;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_issue_hashed_portal_link_and_customer_can_use_it(): void
    {
        [$tenant, $admin, $customer] = $this->fixture();

        $response = $this->actingAs($admin)
            ->post(route('admin.customers.portal-accesses.store', $customer), [
                'label' => 'Accounts',
                'expires_days' => 30,
            ])
            ->assertRedirect()
            ->assertSessionHas('portal_url');

        $portalUrl = $response->getSession()->get('portal_url');
        $this->assertIsString($portalUrl);
        $token = Str::afterLast($portalUrl, '/');

        $access = app(TenantContext::class)->withTenant(
            $tenant,
            fn () => CustomerPortalAccess::firstOrFail(),
        );

        $this->assertNotSame($token, $access->token_hash);
        $this->assertSame(hash('sha256', $token), $access->token_hash);

        auth('web')->logout();
        app('auth')->forgetGuards();

        $this->get($portalUrl)
            ->assertOk()
            ->assertSee('Customer portal')
            ->assertSee('Portal Customer')
            ->assertSee('Approved invoices')
            ->assertSee('Verified payments')
            ->assertDontSee('Other Customer Secret');

        $this->assertNotNull(
            app(TenantContext::class)->withTenant(
                $tenant,
                fn () => $access->fresh()->last_used_at,
            )
        );
    }

    public function test_revoked_and_expired_portal_links_are_rejected(): void
    {
        [$tenant, $admin, $customer] = $this->fixture();

        $token = Str::random(64);
        $access = app(TenantContext::class)->withTenant(
            $tenant,
            fn () => CustomerPortalAccess::create([
                'customer_id' => $customer->id,
                'created_by' => $admin->id,
                'label' => 'Temporary',
                'token_hash' => hash('sha256', $token),
                'expires_at' => now()->addDay(),
            ]),
        );

        $url = route('customer-portal.show', ['token' => $token]);

        $this->get($url)->assertOk();

        $this->actingAs($admin)
            ->delete(route(
                'admin.customers.portal-accesses.destroy',
                [$customer, $access],
            ))
            ->assertRedirect();

        auth('web')->logout();
        app('auth')->forgetGuards();

        $this->get($url)->assertNotFound();

        $expiredToken = Str::random(64);
        app(TenantContext::class)->withTenant(
            $tenant,
            fn () => CustomerPortalAccess::create([
                'customer_id' => $customer->id,
                'created_by' => $admin->id,
                'label' => 'Expired',
                'token_hash' => hash('sha256', $expiredToken),
                'expires_at' => now()->subMinute(),
            ]),
        );

        $this->get(
            route('customer-portal.show', ['token' => $expiredToken])
        )->assertNotFound();
    }

    private function fixture(): array
    {
        $context = app(TenantContext::class);

        $tenant = $context->withPlatformScope(fn () => Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Portal Tenant',
            'slug' => 'portal-'.Str::lower(Str::random(6)),
            'timezone' => 'Asia/Kabul',
            'subscription_status' => 'active',
        ]));

        return $context->withTenant($tenant, function () use ($tenant): array {
            $permissions = collect(['customers:view', 'customers:manage'])
                ->map(fn (string $slug) => Permission::firstOrCreate(
                    ['slug' => $slug],
                    [
                        'name' => str($slug)->replace(':', ' ')->title(),
                        'group' => 'customers',
                    ],
                ));

            $role = Role::create([
                'tenant_id' => $tenant->id,
                'name' => 'Portal Manager',
                'slug' => 'portal-manager',
                'is_system' => false,
            ]);
            $role->permissions()->sync($permissions->pluck('id'));

            $admin = User::create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'name' => 'Portal Manager',
                'email' => 'portal-manager@example.test',
                'password' => Hash::make('password'),
                'role' => 'portal-manager',
                'is_active' => true,
            ]);
            $admin->syncPrimaryRole($role);

            $branch = Branch::create([
                'name' => 'Kabul',
                'code' => 'KBL-PORTAL',
                'is_active' => true,
            ]);

            $territory = Territory::create([
                'branch_id' => $branch->id,
                'code' => 'KBL-PORTAL-T',
                'name' => 'Portal Territory',
                'is_active' => true,
            ]);

            $customer = Customer::create([
                'branch_id' => $branch->id,
                'territory_id' => $territory->id,
                'code' => 'PORTAL-001',
                'name' => 'Portal Customer',
                'phone' => '+93700000001',
                'credit_currency' => 'AFN',
                'credit_terms_days' => 30,
                'created_by' => $admin->id,
                'is_active' => true,
            ]);

            Customer::create([
                'branch_id' => $branch->id,
                'territory_id' => $territory->id,
                'code' => 'PORTAL-002',
                'name' => 'Other Customer Secret',
                'phone' => '+93700000002',
                'credit_currency' => 'AFN',
                'credit_terms_days' => 30,
                'created_by' => $admin->id,
                'is_active' => true,
            ]);

            return [$tenant, $admin, $customer];
        });
    }
}
