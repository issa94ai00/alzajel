<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShiftResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'business_date' => $this->business_date?->toDateString(),
            'opened_at' => $this->opened_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'notes' => $this->notes,
            'opened_by' => new UserResource($this->whenLoaded('openedBy')),
            'closed_by' => new UserResource($this->whenLoaded('closedBy')),
            'shift_accounts' => ShiftAccountResource::collection($this->whenLoaded('shiftAccounts')),
        ];
    }
}
