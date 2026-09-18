<?php
namespace App\Models; use App\Concerns\BelongsToTenant; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Device extends Model { use BelongsToTenant; protected $guarded=[]; protected $casts=['is_active'=>'boolean','registered_at'=>'datetime','last_seen_at'=>'datetime','revoked_at'=>'datetime']; public function user():BelongsTo{return $this->belongsTo(User::class);} public function salesman():BelongsTo{return $this->belongsTo(Salesman::class);} }
