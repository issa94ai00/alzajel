<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCurrencyRequest extends FormRequest
{
    use NormalizesCurrencyTag;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('currency'));
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'size:3', 'uppercase', $this->uniqueCodePerTag()],
            'name' => ['required', 'string', 'max:255'],
            'symbol' => ['nullable', 'string', 'max:10'],
            'tag' => ['nullable', 'string', 'max:50', Rule::unique('currencies', 'tag')->ignore($this->route('currency'))],
            'is_active' => ['boolean'],
        ];
    }
}
