<?php
namespace App\Models;
use App\Concerns\{BelongsToTenant,HasUuid};
use Illuminate\Database\Eloquent\Model;
class SupervisorAssignment extends Model { use BelongsToTenant,HasUuid; protected $guarded=[]; protected $casts=['effective_from'=>'date','effective_to'=>'date']; public function supervisor(){return $this->belongsTo(User::class,'supervisor_user_id');} public function branch(){return $this->belongsTo(Branch::class);} public function territory(){return $this->belongsTo(Territory::class);} }
