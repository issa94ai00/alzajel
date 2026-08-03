<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExchangeRateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'buy_rate' => $this->buy_rate,
            'sell_rate' => $this->sell_rate,
            'updated_by' => $this->updated_by,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
