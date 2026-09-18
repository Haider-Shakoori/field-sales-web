<?php
namespace App\Concerns;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
trait BelongsToTenant { public function tenant(): BelongsTo { return $this->belongsTo(\App\Models\Tenant::class); } }
