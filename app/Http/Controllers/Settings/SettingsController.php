<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSettingsRequest;
use App\Models\ReceiptTemplate;
use App\Models\ReportDefinition;
use App\Reports\ReportRegistry;
use App\Services\ReportRunner;
use App\Support\SettingsRepository;
use App\Support\SystemDefaults;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly ReportRunner $runner,
        private readonly ReportRegistry $registry,
        private readonly SystemDefaults $defaults,
    ) {}

    public function index(Request $request): Response
    {
        // أول زيارة بعد الترقية: نزرع التقارير والقوالب الافتراضية حتى تكون قابلة للتعديل.
        if ($this->defaults->needsSync()) {
            $this->defaults->syncAll();
        }

        $definitions = ReportDefinition::orderBy('sort_order')->orderBy('name')->get();

        return Inertia::render('Admin/Settings/Index', [
            'settings' => $this->nested(),
            'reportDefinitions' => $definitions->map(fn (ReportDefinition $definition) => [
                'id' => $definition->id,
                'key' => $definition->key,
                'builder' => $definition->builder,
                'name' => $definition->name,
                'description' => $definition->description,
                'is_enabled' => $definition->is_enabled,
                'admin_only' => $definition->admin_only,
                'sort_order' => $definition->sort_order,
                'options' => [
                    'hidden_columns' => $definition->option('hidden_columns', []) ?? [],
                    'default_range' => $definition->option('default_range', '') ?? '',
                ],
                'columns' => $this->registry->has($definition->builder)
                    ? $this->runner->columnCatalog($definition, $request->user())
                    : [],
                'is_known' => $this->registry->has($definition->builder),
            ]),
            'receiptTemplates' => ReceiptTemplate::orderBy('subject')->orderByDesc('is_default')->orderBy('name')->get(),
            'builders' => $this->registry->options(),
            'papers' => collect(config('sahli.papers'))
                ->map(fn ($paper, $key) => ['value' => $key, 'label' => $paper['label']])
                ->values(),
            'rangeOptions' => [
                ['value' => 'today', 'label' => 'اليوم'],
                ['value' => 'yesterday', 'label' => 'أمس'],
                ['value' => 'this_week', 'label' => 'هذا الأسبوع'],
                ['value' => 'this_month', 'label' => 'هذا الشهر'],
                ['value' => 'last_30', 'label' => 'آخر 30 يوماً'],
                ['value' => 'all', 'label' => 'كل الفترات'],
            ],
        ]);
    }

    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        $this->settings->setMany($request->settingValues());

        return back()->with('success', 'تم حفظ الإعدادات بنجاح.');
    }

    public function reset(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasRole('admin'), 403);

        $group = $request->string('group')->toString();

        abort_unless(in_array($group, ['company', 'format', 'reports', 'receipts'], true), 422);

        foreach (array_keys($this->settings->group($group)) as $key) {
            $this->settings->forget($key);
        }

        return back()->with('success', 'تمت إعادة هذه المجموعة إلى القيم الافتراضية.');
    }

    /**
     * تحويل المفاتيح المسطّحة إلى بنية متداخلة تناسب نماذج الواجهة.
     *
     * @return array<string, array<string, mixed>>
     */
    private function nested(): array
    {
        $nested = [];

        foreach ($this->settings->effective() as $key => $value) {
            [$group, $name] = array_pad(explode('.', $key, 2), 2, null);

            if ($name === null) {
                continue;
            }

            $nested[$group][$name] = $value;
        }

        return $nested;
    }
}
