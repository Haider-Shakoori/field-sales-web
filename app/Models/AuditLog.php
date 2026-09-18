<?php
namespace App\Models;
use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class AuditLog extends Model { use BelongsToTenant; protected $guarded=[]; protected $casts=['metadata'=>'array']; public function auditable(){return $this->morphTo();} }
