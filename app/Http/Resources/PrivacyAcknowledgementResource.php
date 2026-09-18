<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PrivacyAcknowledgementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'policy_version' => $this->policy_version,
            'acknowledged_at' => $this->acknowledged_at?->toIso8601String(),
            'recorded_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
