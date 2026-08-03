<?php

namespace App\Http\Requests;

use App\Models\ExchangeTransaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExchangeTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', ExchangeTransaction::class);
    }

    public function rules(): array
    {
        return [
            'currency_id' => ['required', 'integer', 'exists:currencies,id'],
            'type' => ['required', Rule::in([ExchangeTransaction::TYPE_BUY, ExchangeTransaction::TYPE_SELL])],
            'amount' => ['required', 'numeric', 'gt:0'],
            'rate' => ['nullable', 'numeric', 'gt:0'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
