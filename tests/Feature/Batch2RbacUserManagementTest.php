<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Salesman;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class Batch2RbacUserManagementTest extends TestCase
{
    use RefreshDatabase;

    private TenantContext $context;

    protected function setUp(): void
    {
        parent::setUp();

        $this->context = app(TenantContext::class);
        $this->context->clear();
    }

    protected function tearDown(): void
    {
        $this->context->clear();

        parent::tearDown();
    }

    public function test_users_role_column_is_not_authorization_truth(): void
    {
        $tenant = $this->tenant('mirror-only');

        $this->platform(function () use ($tenant): void {
            User::create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'name' => 'Mirror Admin',
                'email' => 'mirror@test.local',
                'password' => Hash::make('password'),
                'role' => 'company_admin',
                'is_active' => true,
            ]);
        });

        $this->post('/login', [
            'email' => 'mirror@test.local',
            'password' => 'password',
        ])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_assigned_permission_allows_login_and_user_index(): void
    {
        $tenant = $this->tenant('assigned');
        $user = $this->userWithRole(
            $tenant,
            'admin@assigned.local',
            ['users:view'],
            'company_admin',
        );

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('admin.users.index'));

        $this->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee($user->email);
    }

    public function test_permission_middleware_denies_ungranted_admin_resource(): void
    {
        $tenant = $this->tenant('permission-gate');
        $user = $this->userWithRole(
            $tenant,
            'viewer@permission.local',
            ['users:view'],
            'user_viewer',
        );

        $this->actingAs($user)
            ->get(route('admin.users.index'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('admin.roles.index'))
            ->assertForbidden();
    }

    public function test_cross_tenant_user_route_binding_is_hidden(): void
    {
        $tenantA = $this->tenant('tenant-a');
        $tenantB = $this->tenant('tenant-b');

        $admin = $this->userWithRole(
            $tenantA,
            'admin@a.local',
            ['users:view', 'users:manage'],
            'admin_a',
        );

        $other = $this->platform(fn () => User::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantB->id,
            'name' => 'Other Tenant',
            'email' => 'other@b.local',
            'password' => Hash::make('password'),
            'role' => 'salesman',
            'is_active' => true,
        ]));

        $this->actingAs($admin)
            ->get(route('admin.users.edit', $other))
            ->assertNotFound();
    }

    public function test_user_create_assigns_branch_role_and_audit_event(): void
    {
        $tenant = $this->tenant('user-create');

        [$admin, $branch, $targetRole] = $this->platform(function () use ($tenant): array {
            $branch = Branch::create([
                'tenant_id' => $tenant->id,
                'name' => 'Kabul',
                'code' => 'KBL',
                'is_active' => true,
            ]);

            $adminRole = $this->role(
                $tenant,
                'company_admin',
                ['users:view', 'users:manage'],
                true,
            );

            $targetRole = $this->role(
                $tenant,
                'supervisor',
                ['users:view'],
                true,
            );

            $admin = $this->plainUser($tenant, 'admin@create.local');
            $admin->syncPrimaryRole($adminRole);

            return [$admin, $branch, $targetRole];
        });

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'New Supervisor',
                'email' => 'new@create.local',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'branch_id' => $branch->id,
                'role_id' => $targetRole->id,
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.users.index'));

        $created = $this->tenantScope($tenant, fn () => User::where('email', 'new@create.local')->firstOrFail());

        $this->assertSame($branch->id, $created->branch_id);
        $this->assertSame('supervisor', $created->role);
        $this->assertTrue($created->hasAnyRole(['supervisor']));
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'event' => 'user.created',
            'subject_id' => $created->id,
        ]);
    }

    public function test_user_update_preserves_password_when_left_blank(): void
    {
        $tenant = $this->tenant('user-update');

        [$admin, $target, $role] = $this->platform(function () use ($tenant): array {
            $adminRole = $this->role(
                $tenant,
                'company_admin',
                ['users:view', 'users:manage'],
                true,
            );
            $role = $this->role($tenant, 'auditor', ['users:view'], true);

            $admin = $this->plainUser($tenant, 'admin@update.local');
            $admin->syncPrimaryRole($adminRole);

            $target = $this->plainUser($tenant, 'target@update.local');
            $target->syncPrimaryRole($role);

            return [$admin, $target, $role];
        });

        $oldHash = $target->password;

        $this->actingAs($admin)
            ->put(route('admin.users.update', $target), [
                'name' => 'Updated User',
                'email' => $target->email,
                'password' => '',
                'password_confirmation' => '',
                'branch_id' => '',
                'role_id' => $role->id,
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.users.index'));

        $fresh = $this->tenantScope($tenant, fn () => User::findOrFail($target->id));

        $this->assertSame('Updated User', $fresh->name);
        $this->assertSame($oldHash, $fresh->password);
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'event' => 'user.updated',
            'subject_id' => $target->id,
        ]);
    }

    public function test_user_cannot_deactivate_self(): void
    {
        $tenant = $this->tenant('self-deactivate');
        $admin = $this->userWithRole(
            $tenant,
            'admin@self.local',
            ['users:view', 'users:manage'],
            'company_admin',
        );
        $role = $this->tenantScope($tenant, fn () => $admin->roles()->firstOrFail());

        $this->actingAs($admin)
            ->put(route('admin.users.update', $admin), [
                'name' => $admin->name,
                'email' => $admin->email,
                'password' => '',
                'password_confirmation' => '',
                'branch_id' => '',
                'role_id' => $role->id,
                'is_active' => '0',
            ])
            ->assertSessionHasErrors('is_active');

        $this->assertTrue($this->tenantScope(
            $tenant,
            fn () => User::findOrFail($admin->id)->is_active
        ));
    }

    public function test_cross_tenant_role_cannot_be_assigned_to_user(): void
    {
        $tenantA = $this->tenant('role-a');
        $tenantB = $this->tenant('role-b');

        $admin = $this->userWithRole(
            $tenantA,
            'admin@role-a.local',
            ['users:view', 'users:manage'],
            'company_admin',
        );

        $otherRole = $this->platform(fn () => $this->role(
            $tenantB,
            'foreign_role',
            ['users:view'],
        ));

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'Blocked User',
                'email' => 'blocked@role.local',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'branch_id' => '',
                'role_id' => $otherRole->id,
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('role_id');

        $this->assertDatabaseMissing('users', [
            'tenant_id' => $tenantA->id,
            'email' => 'blocked@role.local',
        ]);
    }

    public function test_custom_role_can_be_created_updated_and_deleted_with_audit(): void
    {
        $tenant = $this->tenant('custom-role');

        $admin = $this->userWithRole(
            $tenant,
            'admin@roles.local',
            ['roles:view', 'roles:manage'],
            'company_admin',
        );

        $permissionA = $this->permission('reports:view');
        $permissionB = $this->permission('audit:view');

        $this->actingAs($admin)
            ->post(route('admin.roles.store'), [
                'name' => 'Regional Viewer',
                'slug' => 'regional_viewer',
                'permission_ids' => [$permissionA->id],
            ])
            ->assertRedirect(route('admin.roles.index'));

        $role = $this->tenantScope(
            $tenant,
            fn () => Role::where('slug', 'regional_viewer')->firstOrFail()
        );

        $this->assertTrue($role->permissions()->whereKey($permissionA->id)->exists());

        $this->actingAs($admin)
            ->put(route('admin.roles.update', $role), [
                'name' => 'Regional Auditor',
                'slug' => 'regional_auditor',
                'permission_ids' => [$permissionA->id, $permissionB->id],
            ])
            ->assertRedirect(route('admin.roles.index'));

        $this->actingAs($admin)
            ->delete(route('admin.roles.destroy', $role))
            ->assertRedirect(route('admin.roles.index'));

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'role.created']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'role.updated']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'role.deleted']);
    }

    public function test_system_role_is_immutable_from_web_management(): void
    {
        $tenant = $this->tenant('system-role');

        [$admin, $systemRole] = $this->platform(function () use ($tenant): array {
            $adminRole = $this->role(
                $tenant,
                'company_admin',
                ['roles:view', 'roles:manage'],
                true,
            );

            $systemRole = $this->role(
                $tenant,
                'salesman',
                ['users:view'],
                true,
            );

            $admin = $this->plainUser($tenant, 'admin@system.local');
            $admin->syncPrimaryRole($adminRole);

            return [$admin, $systemRole];
        });

        $this->actingAs($admin)
            ->get(route('admin.roles.edit', $systemRole))
            ->assertForbidden();

        $this->actingAs($admin)
            ->delete(route('admin.roles.destroy', $systemRole))
            ->assertForbidden();
    }

    public function test_branch_crud_is_tenant_scoped_and_audited(): void
    {
        $tenant = $this->tenant('branch-crud');
        $admin = $this->userWithRole(
            $tenant,
            'admin@branch.local',
            ['branches:view', 'branches:manage'],
            'company_admin',
        );

        $this->actingAs($admin)
            ->post(route('admin.branches.store'), [
                'name' => 'Herat',
                'code' => 'hrt',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.branches.index'));

        $branch = $this->tenantScope(
            $tenant,
            fn () => Branch::where('code', 'HRT')->firstOrFail()
        );

        $this->actingAs($admin)
            ->put(route('admin.branches.update', $branch), [
                'name' => 'Herat Main',
                'code' => 'HRT',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.branches.index'));

        $this->actingAs($admin)
            ->delete(route('admin.branches.destroy', $branch))
            ->assertRedirect(route('admin.branches.index'));

        $this->assertDatabaseMissing('branches', ['id' => $branch->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'branch.created']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'branch.updated']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'branch.deleted']);
    }

    public function test_assigned_branch_cannot_be_deleted(): void
    {
        $tenant = $this->tenant('branch-used');

        [$admin, $branch] = $this->platform(function () use ($tenant): array {
            $role = $this->role(
                $tenant,
                'company_admin',
                ['branches:view', 'branches:manage'],
                true,
            );

            $branch = Branch::create([
                'tenant_id' => $tenant->id,
                'name' => 'Assigned',
                'code' => 'ASG',
                'is_active' => true,
            ]);

            $admin = $this->plainUser($tenant, 'admin@assigned-branch.local', $branch);
            $admin->syncPrimaryRole($role);

            return [$admin, $branch];
        });

        $this->actingAs($admin)
            ->delete(route('admin.branches.destroy', $branch))
            ->assertForbidden();

        $this->assertDatabaseHas('branches', ['id' => $branch->id]);
    }

    public function test_audit_logs_are_tenant_isolated(): void
    {
        $tenantA = $this->tenant('audit-a');
        $tenantB = $this->tenant('audit-b');

        $viewer = $this->userWithRole(
            $tenantA,
            'viewer@audit.local',
            ['audit:view'],
            'auditor',
        );

        $this->platform(function () use ($tenantA, $tenantB): void {
            AuditLog::create([
                'tenant_id' => $tenantA->id,
                'event' => 'tenant-a-event',
            ]);

            AuditLog::create([
                'tenant_id' => $tenantB->id,
                'event' => 'tenant-b-event',
            ]);
        });

        $this->actingAs($viewer)
            ->get(route('admin.audit.index'))
            ->assertOk()
            ->assertSee('tenant-a-event')
            ->assertDontSee('tenant-b-event');
    }

    public function test_tracking_settings_web_route_uses_permission_middleware(): void
    {
        $tenant = $this->tenant('settings-gate');

        $noSettings = $this->userWithRole(
            $tenant,
            'no-settings@test.local',
            ['users:view'],
            'viewer',
        );

        $settingsViewer = $this->userWithRole(
            $tenant,
            'settings@test.local',
            ['settings:view'],
            'settings_viewer',
        );

        $this->actingAs($noSettings)
            ->get(route('tracking.edit'))
            ->assertForbidden();

        $this->actingAs($settingsViewer)
            ->get(route('tracking.edit'))
            ->assertOk();
    }

    public function test_user_with_salesman_profile_cannot_be_hard_deleted(): void
    {
        $tenant = $this->tenant('salesman-delete');

        [$admin, $target] = $this->platform(function () use ($tenant): array {
            $adminRole = $this->role(
                $tenant,
                'company_admin',
                ['users:view', 'users:manage'],
                true,
            );
            $salesmanRole = $this->role($tenant, 'salesman', ['users:view'], true);

            $admin = $this->plainUser($tenant, 'admin@delete.local');
            $admin->syncPrimaryRole($adminRole);

            $target = $this->plainUser($tenant, 'salesman@delete.local');
            $target->syncPrimaryRole($salesmanRole);

            Salesman::create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'user_id' => $target->id,
                'employee_code' => 'SAL-DELETE',
                'first_name' => 'Protected',
                'is_active' => true,
            ]);

            return [$admin, $target];
        });

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $target))
            ->assertSessionHasErrors('user');

        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    private function tenant(string $slug): Tenant
    {
        return Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => str($slug)->replace('-', ' ')->title(),
            'slug' => $slug,
            'timezone' => 'UTC',
            'subscription_status' => 'active',
        ]);
    }

    private function permission(string $slug): Permission
    {
        return Permission::firstOrCreate(
            ['slug' => $slug],
            [
                'name' => str($slug)->replace(':', ' ')->title(),
                'group' => str($slug)->before(':'),
            ]
        );
    }

    private function role(
        Tenant $tenant,
        string $slug,
        array $permissions,
        bool $system = false,
    ): Role {
        $role = Role::create([
            'tenant_id' => $tenant->id,
            'name' => str($slug)->replace('_', ' ')->title(),
            'slug' => $slug,
            'is_system' => $system,
        ]);

        $role->permissions()->sync(
            collect($permissions)
                ->map(fn (string $permission) => $this->permission($permission)->id)
                ->all()
        );

        return $role;
    }

    private function plainUser(
        Tenant $tenant,
        string $email,
        ?Branch $branch = null,
    ): User {
        return User::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'branch_id' => $branch?->id,
            'name' => str($email)->before('@')->replace('.', ' ')->title(),
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => 'salesman',
            'is_active' => true,
        ]);
    }

    private function userWithRole(
        Tenant $tenant,
        string $email,
        array $permissions,
        string $roleSlug,
    ): User {
        return $this->platform(function () use ($tenant, $email, $permissions, $roleSlug): User {
            $role = $this->role($tenant, $roleSlug, $permissions);
            $user = $this->plainUser($tenant, $email);
            $user->syncPrimaryRole($role);

            return $user;
        });
    }

    private function platform(callable $callback): mixed
    {
        return $this->context->withPlatformScope(fn () => $callback());
    }

    private function tenantScope(Tenant $tenant, callable $callback): mixed
    {
        return $this->context->withTenant($tenant, fn () => $callback());
    }
}
