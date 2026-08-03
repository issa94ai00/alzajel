<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
}
