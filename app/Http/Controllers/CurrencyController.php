<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCurrencyRequest;
use App\Http\Requests\UpdateCurrencyRequest;
use App\Models\Currency;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CurrencyController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Currency::class);

        return Inertia::render('Admin/Currencies/Index', [
            'currencies' => Currency::orderByDesc('is_base')->orderBy('code')->get(),
        ]);
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
            'is_active' => $currency->is_base ? true : $request->boolean('is_active', true),
        ]);

        return redirect()->route('currencies.index')->with('success', 'تم تحديث بيانات العملة بنجاح.');
    }
}
