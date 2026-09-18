<?php
namespace App\Models;
use App\Concerns\{BelongsToTenant,HasUuid};
use Illuminate\Database\Eloquent\Model;
class SalesRoute extends Model { use BelongsToTenant,HasUuid; protected $guarded=[]; protected $casts=['visit_days'=>'array','is_active'=>'boolean']; public function territory(){return $this->belongsTo(Territory::class);} public function customers(){return $this->belongsToMany(Customer::class,'route_customers','route_id','customer_id')->withPivot(['sequence','visit_days'])->withTimestamps();} }
