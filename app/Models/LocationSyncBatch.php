<?php
namespace App\Models; use App\Concerns\BelongsToTenant; use Illuminate\Database\Eloquent\Model;
class LocationSyncBatch extends Model { use BelongsToTenant; protected $guarded=[]; protected $casts=['received_at'=>'datetime','processed_at'=>'datetime']; }
