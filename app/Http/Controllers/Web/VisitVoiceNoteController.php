<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\VisitVoiceNote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VisitVoiceNoteController extends Controller
{
    public function show(Request $request, VisitVoiceNote $voiceNote): StreamedResponse
    {
        abort_unless($request->user()->hasPermission('visits:view'),403);
        abort_unless(Storage::disk($voiceNote->disk)->exists($voiceNote->path), 404);
        return response()->stream(function () use ($voiceNote): void { $stream=Storage::disk($voiceNote->disk)->readStream($voiceNote->path); if($stream){ fpassthru($stream); fclose($stream);} },200,['Content-Type'=>$voiceNote->mime_type,'Content-Length'=>(string)$voiceNote->size_bytes,'Accept-Ranges'=>'bytes','Cache-Control'=>'private, max-age=3600']);
    }
}
