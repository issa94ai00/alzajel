<?php

namespace App\Http\Controllers;

use App\Models\ReportDefinition;
use App\Services\ReportRunner;
use App\Support\SettingsRepository;
use App\Support\ValueFormatter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportRunner $runner,
        private readonly SettingsRepository $settings,
        private readonly ValueFormatter $formatter,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Reports/Index', [
            'reports' => $this->runner->availableFor($request->user()),
        ]);
    }

    public function show(Request $request, string $report): Response|RedirectResponse
    {
        $definition = $this->resolve($report);

        $this->assertVisible($request, $definition);

        $payload = $this->runner->run($definition, $this->filtersFrom($request), $request->user());

        return Inertia::render('Reports/Show', [
            ...$payload,
            'reports' => $this->runner->availableFor($request->user()),
            'exportFormats' => $this->runner->exportFormats(),
            'display' => $this->displaySettings(),
        ]);
    }

    public function export(Request $request, string $report): StreamedResponse|View
    {
        $definition = $this->resolve($report);

        $this->assertVisible($request, $definition);

        $format = $request->string('format', 'print')->toString();

        abort_unless(in_array($format, $this->runner->exportFormats(), true), 404, 'صيغة التصدير غير مفعّلة.');

        $filters = $this->filtersFrom($request);

        if ($format === 'csv') {
            return $this->runner->toCsv($definition, $filters, $request->user());
        }

        return $this->printView($request, $definition, $filters);
    }

    private function resolve(string $key): ReportDefinition
    {
        return $this->runner->findByKey($key) ?? abort(404, 'هذا التقرير غير معرّف في النظام.');
    }

    /*
    |--------------------------------------------------------------------------
    | مسارات التقارير القديمة — محفوظة للتوافق مع الروابط والقوائم الحالية
    |--------------------------------------------------------------------------
    */

    public function balances(Request $request): Response|RedirectResponse
    {
        return $this->show($request, 'balances');
    }

    public function daily(Request $request): Response|RedirectResponse
    {
        return $this->show($request, 'daily-operations');
    }

    public function currencyMovement(Request $request): Response|RedirectResponse
    {
        return $this->show($request, 'currency-movement');
    }

    public function profitLoss(Request $request): Response|RedirectResponse
    {
        return $this->show($request, 'profit-loss');
    }

    private function printView(Request $request, ReportDefinition $report, array $filters): View
    {
        $result = $this->runner->rawResult($report, $filters, $request->user());
        $resolved = $this->runner->resolveFilters($report, $filters);
        $filterLabels = collect($this->runner->filterDefinitions($report))->keyBy('key');

        $applied = collect($resolved)
            ->except(['page'])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->mapWithKeys(function ($value, $key) use ($filterLabels) {
                $definition = $filterLabels->get($key);
                $label = $definition['label'] ?? $key;

                if (($definition['type'] ?? null) === 'select') {
                    $option = collect($definition['options'] ?? [])->firstWhere('value', $value)
                        ?? collect($definition['options'] ?? [])->first(fn ($o) => (string) $o['value'] === (string) $value);
                    $value = $option['label'] ?? $value;
                }

                return [$label => $value];
            })
            ->all();

        return view('reports.print', [
            'definition' => $report,
            'result' => $result,
            'appliedFilters' => $applied,
            'company' => [
                'name' => $this->settings->string('company.name', config('app.name')),
                'branch' => $this->settings->string('company.branch'),
                'phone' => $this->settings->string('company.phone'),
            ],
            'headerText' => $this->settings->string('reports.print_header'),
            'footerText' => $this->settings->string('reports.print_footer'),
            'paper' => $this->settings->string('reports.print_paper', 'a4'),
            'printedBy' => $request->user()?->name ?? '—',
            'backUrl' => route('reports.show', array_merge(['report' => $report->key], $filters)),
            'autoPrint' => $request->boolean('autoprint'),
            'format' => fn ($value, $format = 'text') => $this->formatter->format($value, $format),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function filtersFrom(Request $request): array
    {
        return $request->except(['format', 'autoprint', '_token']);
    }

    /**
     * @return array<string, mixed>
     */
    private function displaySettings(): array
    {
        return [
            'decimals' => $this->settings->int('format.decimals', 2),
            'thousands_separator' => $this->settings->bool('format.thousands_separator', true),
            'locale' => $this->settings->string('format.locale', 'ar-SY'),
        ];
    }

    private function assertVisible(Request $request, ReportDefinition $report): void
    {
        abort_unless($report->isVisibleTo($request->user()), 403);
    }
}
