<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('changeStatus', $this->route('user'));
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(array_keys(User::STATUS_LABELS))],
            'reason' => ['nullable', 'string', 'max:255', Rule::requiredIf($this->input('status') === User::STATUS_SUSPENDED)],
        ];
    }

    public function attributes(): array
    {
        return ['status' => 'الحالة', 'reason' => 'السبب'];
    }
}
