<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomerVisit;
use App\Models\VisitVoiceNote;
use App\Support\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VisitVoiceNoteController extends Controller
{
    public function store(Request $request, CustomerVisit $visit): JsonResponse
    {
        abort_unless((int)$visit->user_id === (int)$request->user()->id,404);
        $idempotency=$request->validate(['client_uuid'=>['required','uuid']]);
        $existing=VisitVoiceNote::where('uuid',$idempotency['client_uuid'])->first();
        if($existing){ abort_unless((int)$existing->visit_id===(int)$visit->id && (int)$existing->user_id===(int)$request->user()->id,409); return ApiResponse::success($this->payload($existing)); }
        $v=$request->validate(['audio'=>['required','file','max:12288','mimetypes:audio/mp4,audio/x-m4a,audio/aac,audio/mpeg,audio/ogg,audio/webm,audio/wav,audio/x-wav'],'captured_at'=>['nullable','date'],'duration_seconds'=>['nullable','integer','min:1','max:3600']]);
        $file=$request->file('audio'); $path=$file->store('visits/'.$visit->uuid.'/voice','public');
        $note=VisitVoiceNote::create(['uuid'=>$idempotency['client_uuid'],'visit_id'=>$visit->id,'user_id'=>$request->user()->id,'disk'=>'public','path'=>$path,'mime_type'=>$file->getMimeType() ?: 'audio/mp4','size_bytes'=>$file->getSize(),'duration_seconds'=>$v['duration_seconds']??null,'captured_at'=>isset($v['captured_at']) ? CarbonImmutable::parse($v['captured_at'])->utc() : now()]);
        return ApiResponse::success($this->payload($note),201);
    }
    private function payload(VisitVoiceNote $note): array { return ['id'=>$note->uuid,'mime_type'=>$note->mime_type,'size_bytes'=>$note->size_bytes,'duration_seconds'=>$note->duration_seconds,'captured_at'=>$note->captured_at?->toISOString(),'transcript'=>$note->transcript]; }
}
