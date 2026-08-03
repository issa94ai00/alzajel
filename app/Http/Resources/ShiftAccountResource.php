<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShiftAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'currency' => new CurrencyResource($this->whenLoaded('currency')),
            'opening_balance' => $this->opening_balance,
            'current_balance' => $this->current_balance,
            'closing_balance' => $this->closing_balance,
        ];
    }
}
