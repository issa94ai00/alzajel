<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExchangeTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'shift_id' => $this->shift_id,
            'type' => $this->type,
            'currency_tag' => $this->currency_tag,
            'amount' => $this->amount,
            'rate' => $this->rate,
            'total' => $this->total,
            'profit' => $this->profit,
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'currency' => new CurrencyResource($this->whenLoaded('currency')),
            'counter_currency' => new CurrencyResource($this->whenLoaded('counterCurrency')),
            'user' => new UserResource($this->whenLoaded('user')),
        ];
    }
}
