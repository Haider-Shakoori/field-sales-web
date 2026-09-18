<?php
namespace App\Support;
use Illuminate\Http\JsonResponse;
final class ApiResponse {
 public static function success(mixed $data=null, int $status=200, array $meta=[]): JsonResponse { return response()->json(['success'=>true,'data'=>$data,'meta'=>(object)$meta],$status); }
 public static function error(string $message,int $status=400,mixed $details=null,?string $code=null): JsonResponse { $e=['message'=>$message]; if($code)$e['code']=$code; if($details!==null)$e['details']=$details; return response()->json(['success'=>false,'data'=>null,'meta'=>(object)[],'error'=>$e],$status); }
}
