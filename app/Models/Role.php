<?php
namespace App\Models;
use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
class Role extends Model { use HasUuid; protected $guarded=[]; protected $casts=['is_system'=>'boolean']; public function permissions(){return $this->belongsToMany(Permission::class,'role_permissions');} public function users(){return $this->belongsToMany(User::class,'model_has_roles')->withPivot('tenant_id');} }
