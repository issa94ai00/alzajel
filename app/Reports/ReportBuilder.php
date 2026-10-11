<?php

namespace App\Reports;

use App\Models\User;

interface ReportBuilder
{
    /**
     * وصف الفلاتر التي تظهر في واجهة التقرير.
     *
     * @return array<int, array{key: string, type: string, label: string, options?: array<int, array{value: mixed, label: string}>, default?: mixed}>
     */
    public function filters(): array;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function build(array $filters, ?User $user = null): ReportResult;
}
