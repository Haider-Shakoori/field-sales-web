<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class VisitVoiceNote extends Model
{
    use BelongsToTenant, HasUuid;
    protected $guarded=[];
    protected function casts(): array { return ['captured_at'=>'datetime','duration_seconds'=>'integer','size_bytes'=>'integer']; }
    public function visit(): BelongsTo { return $this->belongsTo(CustomerVisit::class,'visit_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function getUrlAttribute(): string { return Storage::disk($this->disk)->url($this->path); }
}