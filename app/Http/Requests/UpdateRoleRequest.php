<?php

namespace App\Http\Requests;

use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('role'));
    }

    public function rules(): array
    {
        $role = $this->route('role');
        $isSystemRole = in_array($role->name, Permissions::SYSTEM_ROLES, true);

        return [
            // الأدوار المدمجة يمكن تعديل صلاحياتها لكن لا يمكن إعادة تسميتها.
            'name' => $isSystemRole
                ? ['nullable', Rule::in([$role->name])]
                : ['required', 'string', 'max:60', 'regex:/^[\p{Arabic}\w\s\-]+$/u', Rule::unique('roles', 'name')->ignore($role)],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => [Rule::in(Permissions::all())],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'اسم الدور', 'permissions' => 'الصلاحيات'];
    }
}
