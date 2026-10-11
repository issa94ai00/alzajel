<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReceiptTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
    }

    public function rules(): array
    {
        $template = $this->route('receipt_template');

        return [
            'key' => [
                'required', 'string', 'max:40', 'regex:/^[a-z0-9-]+$/',
                Rule::unique('receipt_templates', 'key')->ignore($template?->id),
            ],
            'name' => ['required', 'string', 'max:120'],
            'subject' => ['required', Rule::in(['transaction', 'shift'])],
            'paper' => ['required', Rule::in(array_keys(config('sahli.papers')))],
            'copies' => ['required', 'integer', 'min:1', 'max:5'],
            'is_active' => ['required', 'boolean'],
            'is_default' => ['required', 'boolean'],
            'options.title' => ['nullable', 'string', 'max:120'],
            'options.header_text' => ['nullable', 'string', 'max:255'],
            'options.footer_text' => ['nullable', 'string', 'max:255'],
            'options.show_logo' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'key.regex' => 'المعرّف يجب أن يتكوّن من أحرف إنجليزية صغيرة وأرقام وشرطات فقط.',
        ];
    }
}
