<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Collection;
use App\Models\CommissionPlan;
use App\Models\Customer;
use App\Models\Device;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Salesman;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CommissionEngine;
use App\Services\ReorderRecommendationService;
use App\Services\TenantProvisioningService;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class FinalRoadmapAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_reorder_recommendation_and_commission_are_grounded_in_transactions(): void
    {
        $f=$this->fixture();
        app(TenantContext::class)->withTenant($f['tenant'], function() use($f): void {
            $product=Product::create(['sku'=>'SKU-1','name'=>'Tea','unit'=>'box','base_price'=>100,'is_active'=>true]);
            foreach([now()->subDays(60),now()->subDays(30)] as $i=>$date){$order=Order::create(['user_id'=>$f['salesmanUser']->id,'salesman_id'=>$f['salesman']->id,'device_id'=>$f['device']->id,'customer_id'=>$f['customer']->id,'order_number'=>'REC-'.$i,'ordered_at'=>$date,'payment_type'=>'cash','status'=>'approved','currency'=>'AFN','subtotal'=>1000,'discount_total'=>0,'grand_total'=>1000]); OrderItem::create(['order_id'=>$order->id,'product_id'=>$product->id,'product_sku'=>$product->sku,'product_name'=>$product->name,'unit'=>$product->unit,'quantity'=>10,'unit_price'=>100,'discount_percent'=>0,'discount_amount'=>0,'line_total'=>1000]);}
            $rows=app(ReorderRecommendationService::class)->forTenant(); $this->assertCount(1,$rows); $this->assertSame('Tea',$rows->first()['product_name']);
            $plan=CommissionPlan::create(['name'=>'Sales 2%','metric'=>'sales','rate_percent'=>2,'minimum_source_amount'=>0,'currency'=>'AFN','effective_from'=>now()->subMonth(),'is_active'=>true,'created_by'=>$f['admin']->id]); $plan->salesmen()->sync([$f['salesman']->id=>['tenant_id'=>$f['tenant']->id]]);
            app(CommissionEngine::class)->recalculate(now()->subDays(90)->toImmutable(),now()->addDay()->toImmutable());
            $this->assertDatabaseHas('commission_earnings',['commission_plan_id'=>$plan->id,'salesman_id'=>$f['salesman']->id,'commission_amount'=>20]);
        });
    }

    private function fixture(): array
    {
        $context=app(TenantContext::class); $tenant=$context->withPlatformScope(fn()=>Tenant::create(['uuid'=>(string)Str::uuid(),'name'=>'Roadmap Tenant','slug'=>'roadmap-'.Str::lower(Str::random(6)),'timezone'=>'Asia/Kabul','subscription_status'=>'active']));
        return $context->withTenant($tenant,function() use($tenant): array {$roles=app(TenantProvisioningService::class)->provisionRbac($tenant); $branch=Branch::create(['code'=>'MAIN','name'=>'Main','is_active'=>true]); $admin=User::create(['uuid'=>(string)Str::uuid(),'branch_id'=>$branch->id,'name'=>'Admin','email'=>'roadmap-admin@example.test','password'=>Hash::make('password'),'role'=>'company_admin','is_active'=>true]);$admin->syncPrimaryRole($roles['company_admin']);$salesmanUser=User::create(['uuid'=>(string)Str::uuid(),'branch_id'=>$branch->id,'name'=>'Sales','email'=>'roadmap-sales@example.test','password'=>Hash::make('password'),'role'=>'salesman','is_active'=>true]);$salesmanUser->syncPrimaryRole($roles['salesman']);$salesman=Salesman::create(['user_id'=>$salesmanUser->id,'employee_code'=>'S1','first_name'=>'Sales','is_active'=>true]);$device=Device::create(['user_id'=>$salesmanUser->id,'salesman_id'=>$salesman->id,'device_uuid'=>'roadmap-device','installation_uuid'=>'roadmap-install','is_active'=>true]);$customer=Customer::create(['branch_id'=>$branch->id,'code'=>'C1','name'=>'Customer','created_by'=>$admin->id,'is_active'=>true]);return compact('tenant','branch','admin','salesmanUser','salesman','device','customer');});
    }
}