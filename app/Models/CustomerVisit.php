<?php
namespace App\Models;
use App\Concerns\{BelongsToTenant,HasUuid};
use Illuminate\Database\Eloquent\Model;
class CustomerVisit extends Model { use BelongsToTenant,HasUuid; protected $guarded=[]; protected $casts=['is_planned'=>'boolean','within_geofence'=>'boolean','checked_in_at'=>'datetime','checked_out_at'=>'datetime','media'=>'array','flags'=>'array']; public function customer(){return $this->belongsTo(Customer::class);} public function salesman(){return $this->belongsTo(Salesman::class);} public function workSession(){return $this->belongsTo(WorkSession::class);} }
