<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class CurrentLocation extends Model
{
    use BelongsToTenant;

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

    public function history()
    {
        return $this->belongsTo(LocationHistory::class, 'location_history_id');
    }
}
