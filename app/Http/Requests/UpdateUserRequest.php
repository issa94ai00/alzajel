<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('user'));
    }

    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)->withoutTrashed()],
            'phone' => ['nullable', 'string', 'max:40'],
            'employee_no' => ['nullable', 'string', 'max:40', Rule::unique('users', 'employee_no')->ignore($user)->withoutTrashed()],
            'notes' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(array_keys(User::STATUS_LABELS))],
            'must_change_password' => ['boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $target = $this->route('user');

            // تغيير الحالة له قيوده الخاصة (منع إيقاف النفس أو آخر مدير نشِط).
            if ($this->input('status') !== $target->status
                && $this->user()->cannot('changeStatus', $target)) {
                $validator->errors()->add('status', 'لا يمكن تغيير حالة هذا الحساب.');
            }
        });
    }

    public function attributes(): array
    {
        return [
            'name' => 'الاسم',
            'email' => 'البريد الإلكتروني',
            'phone' => 'رقم الهاتف',
            'employee_no' => 'الرقم الوظيفي',
            'status' => 'الحالة',
        ];
    }
}
