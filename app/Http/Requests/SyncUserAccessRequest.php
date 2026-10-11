<?php

namespace App\Http\Requests;

use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncUserAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageRoles', $this->route('user'));
    }

    public function rules(): array
    {
        return [
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => [Rule::exists('roles', 'name')],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => [Rule::in(Permissions::all())],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $roles = (array) $this->input('roles', []);
            $target = $this->route('user');

            if (! $this->user()->isSuperAdmin()) {
                if (in_array(Permissions::SUPER_ROLE, $roles, true)) {
                    $validator->errors()->add('roles', 'لا تملك صلاحية إسناد دور المدير.');
                }

                // ولا سحبه ممن يملكه.
                if ($target->isSuperAdmin() && ! in_array(Permissions::SUPER_ROLE, $roles, true)) {
                    $validator->errors()->add('roles', 'لا تملك صلاحية سحب دور المدير.');
                }
            }
        });
    }

    public function attributes(): array
    {
        return ['roles' => 'الأدوار', 'permissions' => 'الصلاحيات'];
    }
}
