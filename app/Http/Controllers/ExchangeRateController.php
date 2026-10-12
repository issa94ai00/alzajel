<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateExchangeRateRequest;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Services\SpTodayService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ExchangeRateController extends Controller
{
    public function index(SpTodayService $spTodayService): Response
    {
        $this->authorize('viewAny', ExchangeRate::class);

        $currencies = Currency::with('exchangeRate')
            ->where('is_active', true)
            ->where('is_base', false)
            ->get();

        $spToday = $spTodayService->getRates();

        return Inertia::render('Admin/Rates/Index', [
            'currencies' => $currencies,
            'spToday' => $spToday,
        ]);
    }

    public function update(UpdateExchangeRateRequest $request, Currency $currency): RedirectResponse
    {
        ExchangeRate::updateOrCreate(
            ['currency_id' => $currency->id],
            [
                'buy_rate' => $request->validated('buy_rate'),
                'sell_rate' => $request->validated('sell_rate'),
                'updated_by' => Auth::id(),
            ]
        );

        return redirect()->route('rates.index')->with('success', "تم تحديث سعر صرف {$currency->code}.");
    }

    public function refreshSpToday(SpTodayService $spTodayService): RedirectResponse
    {
        $this->authorize('viewAny', ExchangeRate::class);

        $spTodayService->getRates(forceRefresh: true);

        return redirect()->route('rates.index')->with('success', 'تم تحديث أسعار الصرف من موقع الليرة اليوم (sp-today.com) بنجاح.');
    }

    public function syncAll(Request $request, SpTodayService $spTodayService): RedirectResponse
    {
        $this->authorize('update', ExchangeRate::class);

        if ($request->has('rates') && is_array($request->input('rates'))) {
            $validated = $request->validate([
                'rates' => ['required', 'array'],
                'rates.*.currency_id' => ['required', 'integer', 'exists:currencies,id'],
                'rates.*.buy_rate' => ['required', 'numeric', 'gt:0'],
                'rates.*.sell_rate' => ['required', 'numeric', 'gt:0', 'gte:rates.*.buy_rate'],
            ]);

            $count = 0;
            foreach ($validated['rates'] as $item) {
                ExchangeRate::updateOrCreate(
                    ['currency_id' => $item['currency_id']],
                    [
                        'buy_rate' => $item['buy_rate'],
                        'sell_rate' => $item['sell_rate'],
                        'updated_by' => Auth::id(),
                    ]
                );
                $count++;
            }

            return redirect()->route('rates.index')->with('success', "تم تطبيق واقتباس أسعار الصرف لـ {$count} عملات بنجاح.");
        }

        $spData = $spTodayService->getRates();
        $rates = $spData['rates'] ?? [];

        if (empty($rates)) {
            return redirect()->route('rates.index')->with('error', 'لا توجد بيانات متاحة حالياً من الليرة اليوم للتطبيق.');
        }

        $currencies = Currency::where('is_active', true)->where('is_base', false)->get();
        $count = 0;

        foreach ($currencies as $currency) {
            $code = strtoupper($currency->code);
            if (isset($rates[$code])) {
                ExchangeRate::updateOrCreate(
                    ['currency_id' => $currency->id],
                    [
                        'buy_rate' => $rates[$code]['buy_new'],
                        'sell_rate' => $rates[$code]['sell_new'],
                        'updated_by' => Auth::id(),
                    ]
                );
                $count++;
            }
        }

        return redirect()->route('rates.index')->with('success', "تم تطبيق واقتباس أسعار الليرة اليوم لـ {$count} عملات بنجاح.");
    }
}
