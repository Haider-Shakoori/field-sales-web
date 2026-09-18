<?php
namespace App\Models;
use App\Concerns\{BelongsToTenant,HasUuid};
use Illuminate\Database\Eloquent\Model;
class Territory extends Model { use BelongsToTenant,HasUuid; protected $guarded=[]; protected $casts=['boundary'=>'array','is_active'=>'boolean']; public function branch(){return $this->belongsTo(Branch::class);} }
