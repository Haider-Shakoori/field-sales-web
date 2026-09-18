<?php
namespace App\Models;
use App\Concerns\{BelongsToTenant,HasUuid};
use Illuminate\Database\Eloquent\Model;
class NotificationLog extends Model { use BelongsToTenant,HasUuid; protected $guarded=[]; protected $casts=['data'=>'array','sent_at'=>'datetime','read_at'=>'datetime']; }
