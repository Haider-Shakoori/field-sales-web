<?php
namespace App\Models;
use App\Concerns\{BelongsToTenant,HasUuid};
use Illuminate\Database\Eloquent\Model;
class Customer extends Model { use BelongsToTenant,HasUuid; protected $guarded=[]; protected $casts=['latitude'=>'decimal:7','longitude'=>'decimal:7','credit_limit'=>'decimal:2','is_active'=>'boolean']; public function route(){return $this->belongsTo(SalesRoute::class,'route_id');} public function territory(){return $this->belongsTo(Territory::class);} public function branch(){return $this->belongsTo(Branch::class);} public function salesman(){return $this->belongsTo(Salesman::class,'assigned_salesman_id');} public function visits(){return $this->hasMany(CustomerVisit::class);} public function calls(){return $this->hasMany(CustomerCallActivity::class);} }
