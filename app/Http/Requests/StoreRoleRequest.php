<?php

namespace App\Http\Requests;

use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Role::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60', 'regex:/^[\p{Arabic}\w\s\-]+$/u', Rule::unique('roles', 'name')],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => [Rule::in(Permissions::all())],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'اسم الدور', 'permissions' => 'الصلاحيات'];
    }
}
