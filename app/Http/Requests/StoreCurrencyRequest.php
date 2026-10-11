<?php

namespace App\Http\Requests;

use App\Models\Currency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCurrencyRequest extends FormRequest
{
    use NormalizesCurrencyTag;

    public function authorize(): bool
    {
        return $this->user()->can('create', Currency::class);
    }

    public function rules(): array
    {
        return [
            // الرمز وحده لم يعد فريداً: USD-100 و USD-50 فئتان بنفس الرمز.
            // الفريد هو الرمز مع رمز الفئة، وهو ما يمنع تكرار الفئة نفسها.
            'code' => ['required', 'string', 'size:3', 'uppercase', $this->uniqueCodePerTag()],
            'name' => ['required', 'string', 'max:255'],
            'symbol' => ['nullable', 'string', 'max:10'],
            'tag' => ['nullable', 'string', 'max:50', Rule::unique('currencies', 'tag')],
            'is_active' => ['boolean'],
        ];
    }
}
