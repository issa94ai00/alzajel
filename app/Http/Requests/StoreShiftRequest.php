<?php

namespace App\Http\Requests;

use App\Models\Shift;
use Illuminate\Foundation\Http\FormRequest;

class StoreShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Shift::class);
    }

    public function rules(): array
    {
        return [
            'opening_balances' => ['sometimes', 'array'],
            'opening_balances.*' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
