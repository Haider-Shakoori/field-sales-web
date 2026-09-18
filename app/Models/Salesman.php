<?php
namespace App\Models; use App\Concerns\BelongsToTenant; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Salesman extends Model { use BelongsToTenant; protected $guarded=[]; protected $casts=['is_active'=>'boolean']; public function user():BelongsTo{return $this->belongsTo(User::class);} }
