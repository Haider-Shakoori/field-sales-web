<?php
namespace App\Models; use App\Concerns\BelongsToTenant; use Illuminate\Database\Eloquent\Model;
class CurrentLocation extends Model { use BelongsToTenant; protected $guarded=[]; protected $casts=['is_charging'=>'boolean','is_mock_location'=>'boolean','recorded_at'=>'datetime','received_at'=>'datetime','metadata'=>'array']; }
