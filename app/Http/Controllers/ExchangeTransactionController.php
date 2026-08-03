<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExchangeTransactionRequest;
use App\Models\Currency;
use App\Models\ExchangeTransaction;
use App\Services\ExchangeService;
use App\Services\ShiftService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExchangeTransactionController extends Controller
{
    public function __construct(
        private readonly ExchangeService $exchangeService,
        private readonly ShiftService $shiftService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ExchangeTransaction::class);

        $filters = $request->only(['currency_id', 'type', 'date_from', 'date_to', 'search']);

        return Inertia::render('Transactions/Index', [
            'transactions' => $this->exchangeService->listTransactions($filters),
            'currencies' => Currency::where('is_active', true)->where('is_base', false)->get(),
            'filters' => $filters,
        ]);
    }

    public function create(): Response|RedirectResponse
    {
        $this->authorize('create', ExchangeTransaction::class);

        $shift = $this->shiftService->getCurrentOpenShift();

        if (! $shift) {
            return redirect()->route('shifts.create')->with('error', 'يجب فتح وردية أولاً قبل تسجيل عمليات صرافة.');
        }

        $currencies = Currency::with('exchangeRate')
            ->where('is_active', true)
            ->where('is_base', false)
            ->get();

        return Inertia::render('Transactions/Create', [
            'shift' => $shift,
            'currencies' => $currencies,
        ]);
    }

    public function store(StoreExchangeTransactionRequest $request): RedirectResponse
    {
        $shift = $this->shiftService->getCurrentOpenShift();

        if (! $shift) {
            return redirect()->route('shifts.create')->with('error', 'يجب فتح وردية أولاً قبل تسجيل عمليات صرافة.');
        }

        $this->exchangeService->recordTransaction($shift, $request->user(), $request->validated());

        return redirect()->route('transactions.create')->with('success', 'تم تسجيل العملية بنجاح.');
    }
}
