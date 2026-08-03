<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCurrencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('currency'));
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'size:3', 'uppercase', Rule::unique('currencies', 'code')->ignore($this->route('currency'))],
            'name' => ['required', 'string', 'max:255'],
            'symbol' => ['nullable', 'string', 'max:10'],
            'is_active' => ['boolean'],
        ];
    }
}
