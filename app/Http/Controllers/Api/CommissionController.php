<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CommissionEarning;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommissionController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('commissions:view'),403); $user=$request->user()->load('salesman'); abort_unless($user->salesman?->is_active,403);
        $rows=CommissionEarning::with('plan')->where('salesman_id',$user->salesman->id)->latest('earned_at')->limit(200)->get();
        return ApiResponse::success(['summary'=>$rows->groupBy('currency')->map(fn($group)=>['earned'=>(float)$group->where('status','earned')->sum('commission_amount'),'paid'=>(float)$group->where('status','paid')->sum('commission_amount')])->all(),'earnings'=>$rows->map(fn($e)=>['id'=>$e->uuid,'plan'=>$e->plan?->name,'source_type'=>$e->source_type,'source_amount'=>(float)$e->source_amount,'rate_percent'=>(float)$e->rate_percent,'commission_amount'=>(float)$e->commission_amount,'currency'=>$e->currency,'earned_at'=>$e->earned_at?->toISOString(),'status'=>$e->status])->all()]);
    }
}