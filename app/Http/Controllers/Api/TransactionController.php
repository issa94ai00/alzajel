<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExchangeTransactionRequest;
use App\Http\Resources\CurrencyResource;
use App\Http\Resources\ExchangeTransactionResource;
use App\Http\Resources\ShiftResource;
use App\Models\Currency;
use App\Models\ExchangeTransaction;
use App\Services\ExchangeService;
use App\Services\ShiftService;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function __construct(
        private readonly ExchangeService $exchangeService,
        private readonly ShiftService $shiftService,
    ) {}

    public function index(Request $request): array
    {
        $this->authorize('viewAny', ExchangeTransaction::class);

        $filters = $request->only(['currency_id', 'type', 'date_from', 'date_to', 'search']);

        $transactions = $this->exchangeService->listTransactions($filters);

        return [
            'data' => ExchangeTransactionResource::collection($transactions->items()),
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'total' => $transactions->total(),
            ],
        ];
    }

    public function createFormData(): array
    {
        $this->authorize('create', ExchangeTransaction::class);

        $shift = $this->shiftService->getCurrentOpenShift();

        return [
            'shift' => $shift ? new ShiftResource($shift) : null,
            'currencies' => CurrencyResource::collection(
                Currency::with('exchangeRate')
                    ->where('is_active', true)
                    ->where('is_base', false)
                    ->get()
            ),
        ];
    }

    public function store(StoreExchangeTransactionRequest $request): ExchangeTransactionResource
    {
        $shift = $this->shiftService->getCurrentOpenShift();

        if (! $shift) {
            abort(422, 'يجب فتح وردية أولاً قبل تسجيل عمليات صرافة.');
        }

        $transaction = $this->exchangeService->recordTransaction($shift, $request->user(), $request->validated());

        return new ExchangeTransactionResource($transaction->load(['currency', 'counterCurrency', 'user']));
    }
}
