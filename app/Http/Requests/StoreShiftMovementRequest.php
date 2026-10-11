<?php

namespace App\Http\Requests;

use App\Models\ShiftMovement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreShiftMovementRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(array_keys(ShiftMovement::TYPE_LABELS))],
            'currency_id' => ['required', 'integer', Rule::exists('currencies', 'id')],
            // 18,4 في القاعدة: نمنع الرقم الذي يفيض العمود قبل أن تصرخ القاعدة.
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999999999'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.required' => 'اختر نوع الحركة.',
            'currency_id.required' => 'اختر العملة.',
            'currency_id.exists' => 'العملة المختارة غير موجودة.',
            'amount.required' => 'المبلغ مطلوب.',
            'amount.numeric' => 'المبلغ يجب أن يكون رقماً.',
            'amount.gt' => 'المبلغ يجب أن يكون أكبر من صفر.',
            'notes.max' => 'الملاحظة طويلة جداً (٥٠٠ حرف كحدّ أقصى).',
        ];
    }
}
