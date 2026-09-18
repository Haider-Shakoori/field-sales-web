<?php
namespace App\Models;
use App\Concerns\{BelongsToTenant,HasUuid};
use Illuminate\Database\Eloquent\Model;
class BusinessIntegration extends Model { use BelongsToTenant,HasUuid; protected $guarded=[]; protected $casts=['settings'=>'array','is_active'=>'boolean','last_synced_at'=>'datetime']; protected $hidden=['encrypted_credentials']; }
