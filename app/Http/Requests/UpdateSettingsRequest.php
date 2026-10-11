<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
    }

    public function rules(): array
    {
        return [
            'company.name' => ['required', 'string', 'max:120'],
            'company.branch' => ['nullable', 'string', 'max:120'],
            'company.phone' => ['nullable', 'string', 'max:40'],
            'company.address' => ['nullable', 'string', 'max:255'],
            'company.tax_number' => ['nullable', 'string', 'max:60'],
            'company.logo_text' => ['nullable', 'string', 'max:60'],

            'format.decimals' => ['required', 'integer', 'min:0', 'max:6'],
            'format.thousands_separator' => ['required', 'boolean'],
            'format.locale' => ['required', 'string', 'max:20'],

            'reports.default_range' => ['required', Rule::in(['today', 'yesterday', 'this_week', 'this_month', 'last_30', 'all'])],
            'reports.page_size' => ['required', 'integer', 'min:10', 'max:500'],
            'reports.show_totals' => ['required', 'boolean'],
            'reports.export_formats' => ['array'],
            'reports.export_formats.*' => [Rule::in(['print', 'csv'])],
            'reports.print_paper' => ['required', Rule::in(['a4', 'a5'])],
            'reports.print_header' => ['nullable', 'string', 'max:255'],
            'reports.print_footer' => ['nullable', 'string', 'max:255'],

            'receipts.enabled' => ['required', 'boolean'],
            'receipts.auto_open_after_transaction' => ['required', 'boolean'],
            'receipts.default_transaction_template' => ['nullable', 'string', 'exists:receipt_templates,key'],
            'receipts.default_shift_template' => ['nullable', 'string', 'exists:receipt_templates,key'],
            'receipts.footer_note' => ['nullable', 'string', 'max:255'],
            'receipts.show_operator' => ['required', 'boolean'],
            'receipts.show_customer' => ['required', 'boolean'],
            'receipts.show_notes' => ['required', 'boolean'],
            'receipts.show_rate' => ['required', 'boolean'],
            'receipts.show_profit' => ['required', 'boolean'],
            'receipts.show_signature' => ['required', 'boolean'],
            'receipts.number_prefix' => ['nullable', 'string', 'max:10'],

            'currency.new_lira_notice' => ['required', 'boolean'],
            'currency.old_lira_warn_above' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * تحويل المدخلات المتداخلة إلى مفاتيح إعدادات مسطّحة (company.name ... إلخ).
     *
     * @return array<string, mixed>
     */
    public function settingValues(): array
    {
        return collect($this->validated())
            ->flatMap(fn ($values, $group) => collect($values)
                ->mapWithKeys(fn ($value, $key) => ["{$group}.{$key}" => $value]))
            ->all();
    }
}
