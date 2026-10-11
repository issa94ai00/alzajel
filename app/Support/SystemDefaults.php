<?php

namespace App\Support;

use App\Models\ReceiptTemplate;
use App\Models\ReportDefinition;

/**
 * يزرع التقارير وقوالب الإيصالات الافتراضية المعرّفة في config/sahli.php.
 * العملية idempotent: تُنشئ الصفوف الناقصة فقط ولا تلمس ما عدّله المدير.
 */
class SystemDefaults
{
    public function syncAll(): void
    {
        $this->syncReports();
        $this->syncReceiptTemplates();
    }

    public function syncReports(): int
    {
        $created = 0;

        foreach (config('sahli.reports', []) as $report) {
            $definition = ReportDefinition::firstOrNew(['key' => $report['key']]);

            if ($definition->exists) {
                continue;
            }

            $definition->fill([
                ...$report,
                'is_enabled' => true,
                'options' => ['hidden_columns' => [], 'default_range' => null],
            ])->save();

            $created++;
        }

        return $created;
    }

    public function syncReceiptTemplates(): int
    {
        $created = 0;

        foreach (config('sahli.receipt_templates', []) as $template) {
            $model = ReceiptTemplate::firstOrNew(['key' => $template['key']]);

            if ($model->exists) {
                continue;
            }

            $model->fill([...$template, 'is_active' => true])->save();

            $created++;
        }

        return $created;
    }

    public function needsSync(): bool
    {
        return ReportDefinition::count() === 0 || ReceiptTemplate::count() === 0;
    }
}
