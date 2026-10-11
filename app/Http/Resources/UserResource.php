<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'employee_no' => $this->employee_no,
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
            'must_change_password' => (bool) $this->must_change_password,
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'roles' => $this->getRoleNames(),
            // التطبيق يبني قوائمه وأزراره على هذه القائمة بدل التحقق من اسم الدور.
            'permissions' => $this->getAllPermissions()->pluck('name')->values(),
        ];
    }
}
