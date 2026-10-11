<?php

namespace App\Services;

use App\Models\ReportDefinition;
use App\Models\User;
use App\Reports\ReportRegistry;
use App\Reports\ReportResult;
use App\Support\SettingsRepository;
use App\Support\ValueFormatter;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * يشغّل أي تقرير معرّف في جدول report_definitions عبر المُنشئ المرتبط به،
 * مع تطبيق الإعدادات العامة (النطاق الافتراضي، الأعمدة المخفية، حجم الصفحة).
 */
class ReportRunner
{
    public function __construct(
        private readonly ReportRegistry $registry,
        private readonly SettingsRepository $settings,
        private readonly ValueFormatter $formatter,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function run(ReportDefinition $definition, array $filters, ?User $user = null): array
    {
        $filters = $this->resolveFilters($definition, $filters);
        $builder = $this->registry->make($definition->builder);

        $result = $builder->build($filters, $user)
            ->withoutColumns($definition->option('hidden_columns', []) ?? []);

        $allRows = $result->rows;
        $perPage = max(10, $this->settings->int('reports.page_size', 50));
        $page = max(1, (int) ($filters['page'] ?? 1));

        return [
            'definition' => $this->definitionPayload($definition),
            'filters' => $filters,
            'filterDefinitions' => $builder->filters(),
            'columns' => $result->columns,
            'rows' => $allRows->slice(($page - 1) * $perPage, $perPage)->values()->all(),
            'totals' => $this->settings->bool('reports.show_totals', true) ? $result->totals : [],
            'meta' => array_merge($result->meta, [
                'total_rows' => $allRows->count(),
                'per_page' => $perPage,
                'page' => $page,
                'last_page' => max(1, (int) ceil($allRows->count() / $perPage)),
            ]),
        ];
    }

    /**
     * ناتج كامل بلا ترقيم صفحات — للتصدير والطباعة.
     *
     * @param  array<string, mixed>  $filters
     */
    public function rawResult(ReportDefinition $definition, array $filters, ?User $user = null): ReportResult
    {
        $filters = $this->resolveFilters($definition, $filters);

        return $this->registry->make($definition->builder)
            ->build($filters, $user)
            ->withoutColumns($definition->option('hidden_columns', []) ?? []);
    }

    /**
     * وصف فلاتر التقرير كما يعرضها المُنشئ.
     *
     * @return array<int, array<string, mixed>>
     */
    public function filterDefinitions(ReportDefinition $definition): array
    {
        return $this->registry->make($definition->builder)->filters();
    }

    /**
     * كل الأعمدة التي يستطيع التقرير إنتاجها — تُستخدم في الإعدادات لاختيار
     * الأعمدة المخفية. نشغّل المُنشئ على نطاق فارغ حتى نحصل على الأعمدة بلا بيانات.
     *
     * @return array<int, array{key: string, label: string}>
     */
    public function columnCatalog(ReportDefinition $definition, ?User $user = null): array
    {
        $result = $this->registry->make($definition->builder)->build([
            'date_from' => '1900-01-01',
            'date_to' => '1900-01-01',
        ], $user);

        return collect($result->columns)
            ->map(fn (array $column) => ['key' => $column['key'], 'label' => $column['label']])
            ->all();
    }

    /**
     * التقارير التي يسمح الإعداد والصلاحية بعرضها لهذا المستخدم.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function availableFor(?User $user): Collection
    {
        return $this->definitions()
            ->filter(fn (ReportDefinition $d) => $d->isVisibleTo($user) && $this->registry->has($d->builder))
            ->sortBy([['sort_order', 'asc'], ['name', 'asc']])
            ->map(fn (ReportDefinition $d) => $this->definitionPayload($d))
            ->values();
    }

    /**
     * تعريفات التقارير من قاعدة البيانات، أو الافتراضية من الإعدادات قبل زرعها.
     *
     * @return Collection<int, ReportDefinition>
     */
    public function definitions(): Collection
    {
        $stored = ReportDefinition::orderBy('sort_order')->orderBy('name')->get();

        if ($stored->isNotEmpty()) {
            return $stored;
        }

        return collect(config('sahli.reports', []))
            ->map(fn (array $report) => new ReportDefinition([...$report, 'is_enabled' => true]));
    }

    /**
     * البحث عن تعريف تقرير بمفتاحه، مع الرجوع للتعريف الافتراضي إن لم يُزرع بعد.
     */
    public function findByKey(string $key): ?ReportDefinition
    {
        return $this->definitions()->firstWhere('key', $key);
    }

    /**
     * @return array<int, string>
     */
    public function exportFormats(): array
    {
        return array_values(array_intersect(
            $this->settings->array('reports.export_formats', ['print', 'csv']),
            ['print', 'csv'],
        ));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function resolveFilters(ReportDefinition $definition, array $filters): array
    {
        $filters = array_filter(
            $filters,
            fn ($value) => $value !== null && $value !== '',
        );

        $builderFilters = collect($this->registry->make($definition->builder)->filters());

        // النطاق الزمني الافتراضي من الإعدادات، فقط للتقارير التي تدعمه
        if ($builderFilters->contains(fn ($f) => $f['key'] === 'date_from')
            && ! isset($filters['date_from'])
            && ! isset($filters['date_to'])) {
            $range = $this->defaultRange($definition->option('default_range')
                ?? $this->settings->string('reports.default_range', 'today'));

            $filters = array_merge($filters, $range);
        }

        foreach ($builderFilters as $filter) {
            if (isset($filter['default']) && ! isset($filters[$filter['key']])) {
                $filters[$filter['key']] = $filter['default'];
            }
        }

        return $filters;
    }

    /**
     * @return array{date_from: string, date_to: string}
     */
    public function defaultRange(string $range): array
    {
        $today = CarbonImmutable::today();

        return match ($range) {
            'yesterday' => [
                'date_from' => $today->subDay()->toDateString(),
                'date_to' => $today->subDay()->toDateString(),
            ],
            'this_week' => [
                'date_from' => $today->startOfWeek()->toDateString(),
                'date_to' => $today->toDateString(),
            ],
            'this_month' => [
                'date_from' => $today->startOfMonth()->toDateString(),
                'date_to' => $today->toDateString(),
            ],
            'last_30' => [
                'date_from' => $today->subDays(29)->toDateString(),
                'date_to' => $today->toDateString(),
            ],
            'all' => [
                'date_from' => '',
                'date_to' => '',
            ],
            default => [
                'date_from' => $today->toDateString(),
                'date_to' => $today->toDateString(),
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function toCsv(ReportDefinition $definition, array $filters, ?User $user = null): StreamedResponse
    {
        $result = $this->rawResult($definition, $filters, $user);
        $filename = $definition->key.'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($result) {
            $handle = fopen('php://output', 'wb');

            // BOM حتى تفتح إكسل الملف بترميز عربي صحيح
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, array_column($result->columns, 'label'));

            foreach ($result->rows as $row) {
                fputcsv($handle, array_map(
                    fn (array $column) => $this->csvValue($row[$column['key']] ?? null, $column['format'] ?? 'text'),
                    $result->columns,
                ));
            }

            foreach ($result->totals as $total) {
                fputcsv($handle, [$total['label'], $this->csvValue($total['value'], $total['format'] ?? 'text')]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function definitionPayload(ReportDefinition $definition): array
    {
        return [
            'key' => $definition->key,
            'builder' => $definition->builder,
            'name' => $definition->name,
            'description' => $definition->description,
            'admin_only' => $definition->admin_only,
            'options' => $definition->options ?? [],
        ];
    }

    private function csvValue(mixed $value, string $format = 'text'): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'نعم' : 'لا';
        }

        return $this->formatter->format($value, $format);
    }
}
