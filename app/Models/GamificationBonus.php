<?php
namespace App\Models;
use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class GamificationBonus extends Model {
 use BelongsToTenant;
 protected $guarded=[];
 protected function casts(): array { return ['amount'=>'decimal:4','earned_at'=>'datetime','approved_at'=>'datetime','paid_at'=>'datetime']; }
 public function salesman(): BelongsTo { return $this->belongsTo(Salesman::class); }
 public function target(): BelongsTo { return $this->belongsTo(SalesTarget::class,'sales_target_id'); }
}
