<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * سبب الحذف — مشترك بين حذف العمليات وحذف الورديات.
 *
 * السبب إلزامي عمداً: سجل تدقيق يقول "حُذفت العملية ٤١٢" دون أن يقول لماذا
 * لا يُجيب السؤال الوحيد الذي يُطرح عند المراجعة بعد شهور.
 */
class DeleteRecordRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'سبب الحذف مطلوب.',
            'reason.min' => 'اكتب سبباً مفهوماً (٣ أحرف على الأقل).',
            'reason.max' => 'سبب الحذف طويل جداً (٥٠٠ حرف كحدّ أقصى).',
        ];
    }

    public function reason(): string
    {
        return trim((string) $this->validated('reason'));
    }
}
