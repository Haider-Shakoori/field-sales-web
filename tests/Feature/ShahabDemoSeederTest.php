<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Database\Seeders\ShahabDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ShahabDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_shahab_demo_company_is_seeded_with_kabul_sales_structure(): void
    {
        $this->seed(ShahabDemoSeeder::class);

        $tenant = Tenant::where('slug', 'shahab-demo')->firstOrFail();

        $this->assertSame('Shahab Group - Kabul Demo', $tenant->name);

        $branches = DB::table('branches')
            ->where('tenant_id', $tenant->id)
            ->get();

        $this->assertCount(4, $branches);
        $this->assertTrue($branches->every(fn ($branch) => $branch->geofence_polygon !== null));

        $this->assertSame(
            22,
            DB::table('territories')->where('tenant_id', $tenant->id)->count()
        );

        $this->assertSame(
            4400,
            DB::table('customers')->where('tenant_id', $tenant->id)->count()
        );

        foreach (range(1, 22) as $districtNo) {
            $territoryId = DB::table('territories')
                ->where('tenant_id', $tenant->id)
                ->where('code', sprintf('KBL-D%02d', $districtNo))
                ->value('id');

            $this->assertNotNull($territoryId);
            $this->assertSame(
                200,
                DB::table('customers')->where('territory_id', $territoryId)->count()
            );
        }

        $this->assertSame(
            4,
            DB::table('users')
                ->where('tenant_id', $tenant->id)
                ->where('role', 'sales_manager')
                ->count()
        );
        $this->assertSame(
            4,
            DB::table('supervisors')->where('tenant_id', $tenant->id)->count()
        );
        $this->assertSame(
            52,
            DB::table('salesmen')->where('tenant_id', $tenant->id)->count()
        );
        $this->assertSame(
            8,
            DB::table('routes')->where('tenant_id', $tenant->id)->count()
        );

        $this->assertSame(
            8,
            DB::table('salesman_assignments')
                ->where('tenant_id', $tenant->id)
                ->whereNotNull('route_id')
                ->count()
        );
        $this->assertSame(
            44,
            DB::table('salesman_assignments')
                ->where('tenant_id', $tenant->id)
                ->whereNull('route_id')
                ->count()
        );

        $unassignedCustomers = DB::table('customers')
            ->where('tenant_id', $tenant->id)
            ->whereNull('assigned_salesman_id')
            ->count();

        $this->assertSame(0, $unassignedCustomers);

        foreach ($branches as $branch) {
            $distribution = DB::table('customers')
                ->where('tenant_id', $tenant->id)
                ->where('branch_id', $branch->id)
                ->selectRaw('assigned_salesman_id, COUNT(*) as total')
                ->groupBy('assigned_salesman_id')
                ->pluck('total')
                ->map(fn ($value) => (int) $value)
                ->values();

            $this->assertCount(13, $distribution);
            $this->assertLessThanOrEqual(1, $distribution->max() - $distribution->min());
        }

        $visitCounts = DB::table('customer_visits')
            ->where('tenant_id', $tenant->id)
            ->selectRaw('salesman_id, COUNT(*) as total')
            ->groupBy('salesman_id')
            ->pluck('total');

        $this->assertCount(52, $visitCounts);
        $this->assertTrue($visitCounts->every(fn ($total) => (int) $total >= 100));
        $this->assertSame(5200, $visitCounts->sum());

        $this->assertSame(
            20,
            DB::table('products')->where('tenant_id', $tenant->id)->count()
        );

        $users = DB::table('users')
            ->where('tenant_id', $tenant->id)
            ->whereIn('role', ['sales_manager', 'supervisor', 'salesman'])
            ->get();

        $this->assertCount(60, $users);
        $this->assertTrue($users->every(fn ($user) => str_ends_with($user->email, '@shahab.com')));
        $this->assertTrue($users->every(fn ($user) => $user->phone !== null));
        $this->assertTrue($users->every(fn ($user) => Hash::check('password', $user->password)));
    }
}
