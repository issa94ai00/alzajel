<?php

namespace App\Reports;

use Illuminate\Support\Collection;

/**
 * ناتج تشغيل تقرير: الأعمدة، الصفوف، وبطاقات الإجماليات.
 */
class ReportResult
{
    /**
     * @param  array<int, array{key: string, label: string, format?: string, align?: string}>  $columns
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  array<int, array{key: string, label: string, value: mixed, format?: string}>  $totals
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public array $columns,
        public Collection $rows,
        public array $totals = [],
        public array $meta = [],
    ) {}

    /**
     * @param  array<int, string>  $hidden
     */
    public function withoutColumns(array $hidden): self
    {
        if ($hidden === []) {
            return $this;
        }

        $this->columns = array_values(array_filter(
            $this->columns,
            fn (array $column) => ! in_array($column['key'], $hidden, true),
        ));

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'columns' => $this->columns,
            'rows' => $this->rows->values()->all(),
            'totals' => $this->totals,
            'meta' => $this->meta,
        ];
    }
}
