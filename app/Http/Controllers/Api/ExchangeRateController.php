<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateExchangeRateRequest;
use App\Models\Currency;
use App\Models\ExchangeRate;
use Illuminate\Support\Facades\Auth;

class ExchangeRateController extends Controller
{
    public function update(UpdateExchangeRateRequest $request, Currency $currency): array
    {
        $rate = ExchangeRate::updateOrCreate(
            ['currency_id' => $currency->id],
            [
                'buy_rate' => $request->validated('buy_rate'),
                'sell_rate' => $request->validated('sell_rate'),
                'updated_by' => Auth::id(),
            ]
        );

        return [
            'message' => "تم تحديث سعر صرف {$currency->code}.",
            'exchange_rate' => $rate,
        ];
    }
}
