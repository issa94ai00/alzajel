<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', User::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->withoutTrashed()],
            'phone' => ['nullable', 'string', 'max:40'],
            'employee_no' => ['nullable', 'string', 'max:40', Rule::unique('users', 'employee_no')->withoutTrashed()],
            'notes' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(array_keys(User::STATUS_LABELS))],
            'password' => ['required', 'confirmed', Password::defaults()],
            'must_change_password' => ['boolean'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => [Rule::exists('roles', 'name')],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // منح دور المدير الأعلى محصور بمن يملكه أصلاً.
            if (in_array(Permissions::SUPER_ROLE, (array) $this->input('roles', []), true)
                && ! $this->user()->isSuperAdmin()) {
                $validator->errors()->add('roles', 'لا تملك صلاحية إسناد دور المدير.');
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
            'password' => 'كلمة المرور',
            'roles' => 'الأدوار',
        ];
    }
}
