<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CommissionEarning;
use App\Models\CommissionPlan;
use App\Models\Product;
use App\Models\Salesman;
use App\Models\Territory;
use App\Services\AuditLogger;
use App\Services\CommissionEngine;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CommissionController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);
        $user=$request->user()->load(['salesman','tenant']); $tz=$user->tenant?->timezone ?: config('app.timezone');
        $from=$validated['date_from'] ?? now($tz)->startOfMonth()->toDateString();
        $to=$validated['date_to'] ?? now($tz)->toDateString();
        $earnings=CommissionEarning::with(['plan','salesman'])->whereBetween('earned_at',[CarbonImmutable::parse($from,$tz)->utc(),CarbonImmutable::parse($to.' 23:59:59',$tz)->utc()])
            ->when($user->hasAnyRole(['salesman']), fn($q)=>$q->where('salesman_id',$user->salesman?->id ?? 0))->latest('earned_at')->limit(500)->get();
        return view('admin.commissions.index',[
            'plans'=>CommissionPlan::with(['salesmen','product','territory'])->latest()->get(), 'earnings'=>$earnings,
            'salesmen'=>Salesman::active()->orderBy('employee_code')->get(),'products'=>Product::active()->orderBy('name')->get(),'territories'=>Territory::active()->orderBy('name')->get(),
            'from'=>$from,'to'=>$to,'canManage'=>$user->hasPermission('commissions:manage'),
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $tenantId=$request->user()->tenant_id;
        $v=$request->validate(['name'=>['required','string','max:160'],'metric'=>['required',Rule::in(CommissionPlan::METRICS)],'rate_percent'=>['required','numeric','gt:0','lte:100'],'minimum_source_amount'=>['nullable','numeric','min:0'],'currency'=>['required','string','size:3'],'product_id'=>['nullable','integer',Rule::exists('products','id')->where('tenant_id',$tenantId)],'territory_id'=>['nullable','integer',Rule::exists('territories','id')->where('tenant_id',$tenantId)],'effective_from'=>['required','date'],'effective_to'=>['nullable','date','after_or_equal:effective_from'],'salesman_ids'=>['required','array','min:1'],'salesman_ids.*'=>['integer',Rule::exists('salesmen','id')->where('tenant_id',$tenantId)]]);
        if($v['metric']==='collections') $v['product_id']=null;
        $plan=CommissionPlan::create(['name'=>$v['name'],'metric'=>$v['metric'],'rate_percent'=>$v['rate_percent'],'minimum_source_amount'=>$v['minimum_source_amount']??0,'currency'=>strtoupper($v['currency']),'product_id'=>$v['product_id']??null,'territory_id'=>$v['territory_id']??null,'effective_from'=>$v['effective_from'],'effective_to'=>$v['effective_to']??null,'is_active'=>true,'created_by'=>$request->user()->id]);
        $sync=[]; foreach($v['salesman_ids'] as $id) $sync[$id]=['tenant_id'=>$tenantId]; $plan->salesmen()->sync($sync);
        $audit->record('commission_plan.created',$plan,[],['metric'=>$plan->metric,'rate_percent'=>$plan->rate_percent]);
        return back()->with('status',__('Commission plan created.'));
    }

    public function recalculate(Request $request, CommissionEngine $engine): RedirectResponse
    {
        $user=$request->user()->load('tenant'); $tz=$user->tenant?->timezone ?: config('app.timezone');
        $v=$request->validate(['date_from'=>['required','date'],'date_to'=>['required','date','after_or_equal:date_from']]);
        $result=$engine->recalculate(CarbonImmutable::parse($v['date_from'].' 00:00:00',$tz)->utc(),CarbonImmutable::parse($v['date_to'].' 23:59:59',$tz)->utc());
        return back()->with('status',__('Commission recalculation created :count earning rows.', ['count'=>$result['created']]));
    }

    public function markPaid(Request $request, CommissionEarning $earning, AuditLogger $audit): RedirectResponse
    {
        if($earning->status==='paid') return back(); $before=['status'=>$earning->status]; $earning->update(['status'=>'paid','paid_at'=>now(),'paid_by'=>$request->user()->id]); $audit->record('commission_earning.paid',$earning,$before,['status'=>'paid']); return back()->with('status',__('Commission marked paid.'));
    }
}