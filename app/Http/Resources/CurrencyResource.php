<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CurrencyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'symbol' => $this->symbol,
            'is_base' => $this->is_base,
            'is_active' => $this->is_active,
            'exchange_rate' => $this->whenLoaded('exchangeRate', fn () => $this->exchangeRate ? new ExchangeRateResource($this->exchangeRate) : null),
        ];
    }
}
