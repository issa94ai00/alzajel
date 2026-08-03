<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCurrencyRequest;
use App\Http\Requests\UpdateCurrencyRequest;
use App\Http\Resources\CurrencyResource;
use App\Models\Currency;

class CurrencyController extends Controller
{
    public function index(): array
    {
        $currencies = Currency::with('exchangeRate')
            ->where('is_active', true)
            ->where('is_base', false)
            ->get();

        return [
            'data' => CurrencyResource::collection($currencies),
        ];
    }

    public function adminIndex(): array
    {
        $this->authorize('viewAny', Currency::class);

        $currencies = Currency::orderByDesc('is_base')->orderBy('code')->get();

        return [
            'data' => CurrencyResource::collection($currencies),
        ];
    }

    public function store(StoreCurrencyRequest $request): CurrencyResource
    {
        $currency = Currency::create([
            'code' => $request->validated('code'),
            'name' => $request->validated('name'),
            'symbol' => $request->validated('symbol'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return new CurrencyResource($currency);
    }

    public function update(UpdateCurrencyRequest $request, Currency $currency): CurrencyResource
    {
        $currency->update([
            'code' => $request->validated('code'),
            'name' => $request->validated('name'),
            'symbol' => $request->validated('symbol'),
            'is_active' => $currency->is_base ? true : $request->boolean('is_active', true),
        ]);

        return new CurrencyResource($currency);
    }
}
