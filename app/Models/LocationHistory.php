<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class LocationHistory extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $table = 'location_history';

    protected $guarded = [];

    protected $casts = [
        'is_charging' => 'boolean',
        'is_mock_location' => 'boolean',
        'recorded_at' => 'datetime',
        'received_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function salesman()
    {
        return $this->belongsTo(Salesman::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function device()
    {
        return $this->belongsTo(Device::class);
    }
}
