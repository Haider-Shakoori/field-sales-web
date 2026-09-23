<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class StockReturnPermissionBackfillTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_standard_roles_receive_stock_and_return_permissions_idempotently(): void
    {
        $tenant = app(TenantContext::class)->withPlatformScope(
            fn () => Tenant::create([
                'uuid' => (string) Str::uuid(),
                'name' => 'Existing Tenant',
                'slug' => 'existing-'.Str::lower(Str::random(8)),
                'timezone' => 'Asia/Kabul',
                'subscription_status' => 'active',
            ]),
        );

        [$admin, $salesman, $custom] = app(TenantContext::class)->withTenant(
            $tenant,
            fn () => [
                Role::create([
                    'name' => 'Company Admin',
                    'slug' => 'company_admin',
                    'is_system' => true,
                ]),
                Role::create([
                    'name' => 'Salesman',
                    'slug' => 'salesman',
                    'is_system' => true,
                ]),
                Role::create([
                    'name' => 'Custom Role',
                    'slug' => 'custom-role',
                    'is_system' => false,
                ]),
            ],
        );

        DB::table('role_permissions')->delete();
        DB::table('permissions')
            ->whereIn('slug', [
                'stock:view',
                'stock:manage',
                'returns:view',
                'returns:manage',
            ])
            ->delete();

        $migration = require base_path(
            'database/migrations/2026_09_24_000000_backfill_stock_return_permissions.php',
        );

        $migration->up();
        $migration->up();

        $permissionIds = DB::table('permissions')
            ->whereIn('slug', [
                'stock:view',
                'stock:manage',
                'returns:view',
                'returns:manage',
            ])
            ->pluck('id', 'slug');

        $this->assertCount(4, $permissionIds);

        $this->assertRoleHasPermissions($admin->id, [
            'stock:view',
            'stock:manage',
            'returns:view',
            'returns:manage',
        ]);

        $this->assertRoleHasPermissions($salesman->id, [
            'stock:view',
            'returns:view',
        ]);

        $this->assertSame(
            0,
            DB::table('role_permissions')
                ->where('role_id', $salesman->id)
                ->whereIn('permission_id', [
                    $permissionIds['stock:manage'],
                    $permissionIds['returns:manage'],
                ])
                ->count(),
        );

        $this->assertSame(
            0,
            DB::table('role_permissions')
                ->where('role_id', $custom->id)
                ->count(),
        );

        $this->assertSame(
            6,
            DB::table('role_permissions')
                ->whereIn('role_id', [$admin->id, $salesman->id])
                ->count(),
        );
    }

    private function assertRoleHasPermissions(
        int $roleId,
        array $slugs,
    ): void {
        $actual = DB::table('role_permissions')
            ->join(
                'permissions',
                'permissions.id',
                '=',
                'role_permissions.permission_id',
            )
            ->where('role_permissions.role_id', $roleId)
            ->whereIn('permissions.slug', $slugs)
            ->pluck('permissions.slug')
            ->sort()
            ->values()
            ->all();

        $expected = collect($slugs)->sort()->values()->all();

        $this->assertSame($expected, $actual);
    }
}
