<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCurrencyRequest;
use App\Http\Requests\UpdateCurrencyRequest;
use App\Models\Currency;
use App\Services\BaseCurrencySwitcher;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CurrencyController extends Controller
{
    public function __construct(
        private readonly BaseCurrencySwitcher $switcher,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Currency::class);

        return Inertia::render('Admin/Currencies/Index', [
            'currencies' => Currency::with('exchangeRate')->orderByDesc('is_base')->orderBy('code')->get(),
            // معاينة أثر تغيير الأساس تُحسب على الخادم بنفس شيفرة التنفيذ،
            // حتى لا تفترق الأرقام المعروضة عمّا سيُحفظ فعلاً.
            'basePreview' => $this->previewFrom($request),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function previewFrom(Request $request): ?array
    {
        $targetId = $request->integer('base_preview');

        if (! $targetId) {
            return null;
        }

        $target = Currency::find($targetId);

        if (! $target) {
            return null;
        }

        $mode = $request->string('mode')->toString();

        if (! in_array($mode, BaseCurrencySwitcher::MODES, true)) {
            $mode = BaseCurrencySwitcher::MODE_CONVERT;
        }

        return $this->switcher->preview($target, $mode);
    }

    /**
     * اعتماد عملة أساس جديدة وإعادة بناء جدول الأسعار حولها.
     */
    public function makeBase(Request $request, Currency $currency): RedirectResponse
    {
        $this->authorize('update', $currency);

        $validated = $request->validate([
            'mode' => ['required', 'string', 'in:'.implode(',', BaseCurrencySwitcher::MODES)],
        ]);

        $this->switcher->apply($currency, $validated['mode'], $request->user());

        return redirect()
            ->route('currencies.index')
            ->with('success', "تم اعتماد {$currency->code} عملةً أساساً وإعادة بناء الأسعار.");
    }

    public function create(): Response
    {
        $this->authorize('create', Currency::class);

        return Inertia::render('Admin/Currencies/Create');
    }

    public function store(StoreCurrencyRequest $request): RedirectResponse
    {
        Currency::create([
            'code' => $request->validated('code'),
            'name' => $request->validated('name'),
            'symbol' => $request->validated('symbol'),
            'tag' => $request->validated('tag'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('currencies.index')->with('success', 'تمت إضافة العملة بنجاح.');
    }

    public function edit(Currency $currency): Response
    {
        $this->authorize('update', $currency);

        return Inertia::render('Admin/Currencies/Edit', [
            'currency' => $currency,
        ]);
    }

    public function update(UpdateCurrencyRequest $request, Currency $currency): RedirectResponse
    {
        $currency->update([
            'code' => $request->validated('code'),
            'name' => $request->validated('name'),
            'symbol' => $request->validated('symbol'),
            'tag' => $request->validated('tag'),
            'is_active' => $currency->is_base ? true : $request->boolean('is_active', true),
        ]);

        return redirect()->route('currencies.index')->with('success', 'تم تحديث بيانات العملة بنجاح.');
    }
}
