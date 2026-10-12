<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCurrencyRequest;
use App\Http\Requests\UpdateCurrencyRequest;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Services\BaseCurrencySwitcher;
use App\Services\SpTodayService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class CurrencyController extends Controller
{
    public function __construct(
        private readonly BaseCurrencySwitcher $switcher,
    ) {}

    public function index(Request $request, SpTodayService $spTodayService): Response
    {
        $this->authorize('viewAny', Currency::class);

        return Inertia::render('Admin/Currencies/Index', [
            'currencies' => Currency::with('exchangeRate')->orderByDesc('is_base')->orderBy('code')->get(),
            'spToday' => $spTodayService->getRates(),
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

    /**
     * تفعيل أو تعطيل العملة بنقرة واحدة سريعة
     */
    public function toggleStatus(Currency $currency): RedirectResponse
    {
        $this->authorize('update', $currency);

        if ($currency->is_base) {
            return back()->with('error', 'لا يمكن تعطيل العملة الأساس.');
        }

        $currency->is_active = ! $currency->is_active;
        $currency->save();

        $actionText = $currency->is_active ? 'تفعيل' : 'تعطيل';
        return back()->with('success', "تم {$actionText} عملة {$currency->code} بنجاح.");
    }

    /**
     * استيراد وإضافة عملات مباشرة من مؤشر الليرة اليوم (sp-today) مع أسعار الصرف
     */
    public function importFromSpToday(Request $request, SpTodayService $spTodayService): RedirectResponse
    {
        $this->authorize('create', Currency::class);

        $validated = $request->validate([
            'codes' => ['required', 'array', 'min:1'],
            'codes.*' => ['required', 'string', 'size:3'],
            'include_rates' => ['boolean'],
        ]);

        $codes = array_map('strtoupper', $validated['codes']);
        $includeRates = $request->boolean('include_rates', true);

        $spData = $spTodayService->getRates();
        $marketRates = $spData['rates'] ?? [];

        $symbols = [
            'USD' => '$',
            'EUR' => '€',
            'TRY' => '₺',
            'SAR' => 'ر.س',
            'AED' => 'د.إ',
            'GBP' => '£',
            'JOD' => 'د.أ',
            'KWD' => 'د.ك',
            'QAR' => 'ر.ق',
            'BHD' => 'د.ب',
            'OMR' => 'ر.ع',
            'EGP' => 'ج.م',
            'LYD' => 'د.ل',
            'CAD' => 'C$',
            'AUD' => 'A$',
            'CHF' => 'CHF',
            'SEK' => 'kr',
            'NOK' => 'kr',
            'DKK' => 'kr',
            'RUB' => '₽',
            'IQD' => 'د.ع',
            'SYP' => 'ل.س',
            'DZD' => 'د.ج',
            'MAD' => 'د.م',
            'TND' => 'د.ت',
            'MYR' => 'RM',
            'BRL' => 'R$',
            'NZD' => 'NZ$',
            'ZAR' => 'R',
            'IRR' => '﷼',
            'SGD' => 'S$',
        ];

        $count = 0;
        foreach ($codes as $code) {
            $marketInfo = $marketRates[$code] ?? null;
            if (! $marketInfo) {
                continue;
            }

            // البحث عن العملة بدون فئة
            $currency = Currency::where('code', $code)->whereNull('tag')->first();

            if (! $currency) {
                $currency = Currency::create([
                    'code' => $code,
                    'name' => $marketInfo['name_ar'] ?? $code,
                    'symbol' => $symbols[$code] ?? ($marketInfo['flag'] ?? null),
                    'tag' => null,
                    'is_active' => true,
                ]);
            } else {
                $currency->is_active = true;
                $currency->save();
            }

            if ($includeRates && isset($marketInfo['buy_new'], $marketInfo['sell_new'])) {
                ExchangeRate::updateOrCreate(
                    ['currency_id' => $currency->id],
                    [
                        'buy_rate' => $marketInfo['buy_new'],
                        'sell_rate' => $marketInfo['sell_new'],
                        'updated_by' => Auth::id(),
                    ]
                );
            }

            $count++;
        }

        return redirect()->route('currencies.index')->with('success', "تم استيراد وتفعيل {$count} عملات بنجاح من مؤشر الليرة اليوم.");
    }

    /**
     * تفعيل أو تعطيل مجموعة عملات دفعة واحدة
     */
    public function batchStatus(Request $request): RedirectResponse
    {
        $this->authorize('update', Currency::class);

        $validated = $request->validate([
            'currency_ids' => ['required', 'array', 'min:1'],
            'currency_ids.*' => ['required', 'integer', 'exists:currencies,id'],
            'is_active' => ['required', 'boolean'],
        ]);

        $isActive = $validated['is_active'];
        $affected = Currency::whereIn('id', $validated['currency_ids'])
            ->where('is_base', false)
            ->update(['is_active' => $isActive]);

        $action = $isActive ? 'تفعيل' : 'تعطيل';
        return back()->with('success', "تم {$action} {$affected} عملات بنجاح.");
    }
}
