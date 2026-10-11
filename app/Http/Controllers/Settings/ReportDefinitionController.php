<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\ReportDefinition;
use App\Reports\ReportRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReportDefinitionController extends Controller
{
    public function __construct(private readonly ReportRegistry $registry) {}

    /**
     * حفظ تعديلات جماعية على قائمة التقارير (تفعيل، ترتيب، أعمدة مخفية...).
     */
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'reports' => ['required', 'array'],
            'reports.*.id' => ['required', 'integer', 'exists:report_definitions,id'],
            'reports.*.name' => ['required', 'string', 'max:120'],
            'reports.*.description' => ['nullable', 'string', 'max:255'],
            'reports.*.is_enabled' => ['required', 'boolean'],
            'reports.*.admin_only' => ['required', 'boolean'],
            'reports.*.sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'reports.*.options.hidden_columns' => ['array'],
            'reports.*.options.hidden_columns.*' => ['string', 'max:60'],
            'reports.*.options.default_range' => ['nullable', Rule::in(['today', 'yesterday', 'this_week', 'this_month', 'last_30', 'all'])],
        ]);

        foreach ($data['reports'] as $row) {
            ReportDefinition::whereKey($row['id'])->update([
                'name' => $row['name'],
                'description' => $row['description'] ?? null,
                'is_enabled' => $row['is_enabled'],
                'admin_only' => $row['admin_only'],
                'sort_order' => $row['sort_order'],
                'options' => [
                    'hidden_columns' => $row['options']['hidden_columns'] ?? [],
                    'default_range' => $row['options']['default_range'] ?? null,
                ],
            ]);
        }

        return back()->with('success', 'تم تحديث إعدادات التقارير.');
    }

    /**
     * إنشاء نسخة جديدة من نوع تقرير موجود (مثلاً "عمليات اليوم" و"عمليات الشهر").
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:40', 'regex:/^[a-z0-9-]+$/', 'unique:report_definitions,key'],
            'builder' => ['required', Rule::in($this->registry->keys())],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'admin_only' => ['required', 'boolean'],
            'is_enabled' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'options.default_range' => ['nullable', Rule::in(['today', 'yesterday', 'this_week', 'this_month', 'last_30', 'all'])],
        ], [
            'key.regex' => 'المعرّف يجب أن يتكوّن من أحرف إنجليزية صغيرة وأرقام وشرطات فقط.',
        ]);

        ReportDefinition::create([
            ...$data,
            'sort_order' => $data['sort_order'] ?? ((ReportDefinition::max('sort_order') ?? 0) + 10),
            'options' => [
                'hidden_columns' => [],
                'default_range' => $data['options']['default_range'] ?? null,
            ],
        ]);

        return back()->with('success', 'تمت إضافة التقرير.');
    }

    public function destroy(ReportDefinition $reportDefinition): RedirectResponse
    {
        $reportDefinition->delete();

        return back()->with('success', 'تم حذف التقرير من القائمة.');
    }
}
