<?php
namespace App\Models; use App\Concerns\BelongsToTenant; use Illuminate\Database\Eloquent\Model;
class PrivacyAcknowledgement extends Model { use BelongsToTenant; protected $guarded=[]; protected $casts=['acknowledged_at'=>'datetime','recorded_at'=>'datetime']; }
