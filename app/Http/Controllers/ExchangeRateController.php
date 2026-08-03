<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateExchangeRateRequest;
use App\Models\Currency;
use App\Models\ExchangeRate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ExchangeRateController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', ExchangeRate::class);

        $currencies = Currency::with('exchangeRate')
            ->where('is_active', true)
            ->where('is_base', false)
            ->get();

        return Inertia::render('Admin/Rates/Index', [
            'currencies' => $currencies,
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
}
