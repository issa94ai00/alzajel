<?php

namespace App\Http\Requests;

use App\Models\ExchangeRate;
use Illuminate\Foundation\Http\FormRequest;

class UpdateExchangeRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', ExchangeRate::class);
    }

    public function rules(): array
    {
        return [
            'buy_rate' => ['required', 'numeric', 'gt:0'],
            'sell_rate' => ['required', 'numeric', 'gt:0', 'gte:buy_rate'],
        ];
    }
}
