<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeleteRecordRequest;
use App\Http\Requests\StoreExchangeTransactionRequest;
use App\Models\Currency;
use App\Models\ExchangeTransaction;
use App\Models\User;
use App\Services\ExchangeService;
use App\Services\ShiftService;
use App\Support\Permissions;
use App\Support\SettingsRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExchangeTransactionController extends Controller
{
    public function __construct(
        private readonly ExchangeService $exchangeService,
        private readonly ShiftService $shiftService,
        private readonly SettingsRepository $settings,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ExchangeTransaction::class);

        $user = $request->user();
        $canSeeAll = $user->can(Permissions::TRANSACTIONS_VIEW_ANY_USER);
        $canSeeTrashed = $user->can('viewTrashed', ExchangeTransaction::class);

        $filters = $request->only(['currency_id', 'type', 'date_from', 'date_to', 'search', 'user_id', 'trashed']);

        // من لا يملك صلاحية الاطلاع على عمليات الآخرين يُقيَّد بعملياته هو.
        if (! $canSeeAll) {
            $filters['user_id'] = $user->id;
        }

        // سلّة المحذوفات محجوبة عمّن لا يملك صلاحية الحذف.
        if (! $canSeeTrashed || ! in_array($filters['trashed'] ?? null, ['only', 'with'], true)) {
            unset($filters['trashed']);
        }

        return Inertia::render('Transactions/Index', [
            'transactions' => $this->exchangeService->listTransactions($filters)
                ->through(fn (ExchangeTransaction $transaction) => $this->summarize($transaction, $user)),
            'currencies' => Currency::where('is_active', true)->where('is_base', false)->get(),
            'filters' => $filters + ['trashed' => $filters['trashed'] ?? ''],
            'canSeeAll' => $canSeeAll,
            'canSeeTrashed' => $canSeeTrashed,
            'trashedCount' => $canSeeTrashed ? ExchangeTransaction::onlyTrashed()->count() : 0,
            'users' => $canSeeAll ? User::orderBy('name')->get(['id', 'name']) : [],
        ]);
    }

    /**
     * سطر العملية كما تراه الواجهة، ومعه ما يُسمح به عليه وأثر حذفه.
     *
     * @return array<string, mixed>
     */
    private function summarize(ExchangeTransaction $transaction, User $viewer): array
    {
        return [
            'id' => $transaction->id,
            'created_at' => $transaction->created_at?->toIso8601String(),
            'type' => $transaction->type,
            'currency' => $transaction->currency?->only(['id', 'code', 'name']),
            'currency_tag' => $transaction->currency_tag,
            'amount' => (string) $transaction->amount,
            'rate' => (string) $transaction->rate,
            'total' => (string) $transaction->total,
            'customer_name' => $transaction->customer_name,
            'user' => $transaction->user?->only(['id', 'name']),
            'shift_id' => $transaction->shift_id,
            'shift_status' => $transaction->shift?->status,
            'deleted_at' => $transaction->deleted_at?->toIso8601String(),
            'deleted_by' => $transaction->deletedBy?->name,
            'deletion_reason' => $transaction->deletion_reason,
            'deleted_with_shift' => $transaction->deleted_with_shift,
            'can' => [
                'delete' => $viewer->can('delete', $transaction),
                'restore' => $viewer->can('restore', $transaction),
            ],
        ];
    }

    /**
     * حذف ناعم لعملية: تختفي من السجلات والتقارير، ويُعكس أثرها على أرصدة
     * الوردية، ويبقى السطر ومعه من حذفه ولماذا.
     */
    public function destroy(DeleteRecordRequest $request, ExchangeTransaction $transaction): RedirectResponse
    {
        $this->authorize('delete', $transaction);

        $this->exchangeService->deleteTransaction($transaction, $request->user(), $request->reason());

        return back()->with('success', "تم حذف العملية رقم {$transaction->id} وعكس أثرها على أرصدة الوردية.");
    }

    public function restore(Request $request, ExchangeTransaction $transaction): RedirectResponse
    {
        $this->authorize('restore', $transaction);

        $this->exchangeService->restoreTransaction($transaction, $request->user());

        return back()->with('success', "تمت استعادة العملية رقم {$transaction->id}.");
    }

    public function create(Request $request): Response|RedirectResponse
    {
        $this->authorize('create', ExchangeTransaction::class);

        $shift = $this->shiftService->getCurrentOpenShift($request->user());

        if (! $shift) {
            return redirect()->route('shifts.create')->with('error', 'يجب فتح ورديتك أولاً قبل تسجيل عمليات صرافة.');
        }

        $currencies = Currency::with('exchangeRate')
            ->where('is_active', true)
            ->where('is_base', false)
            ->get();

        $baseCurrency = Currency::where('is_base', true)->first();

        // أرصدة الوردية الحالية — ليرى الصرّاف الكفاية قبل الإرسال بدل أن
        // يكتشف النقص من رسالة خطأ بعد الحفظ.
        $balances = $shift->shiftAccounts()
            ->get()
            ->mapWithKeys(fn ($account) => [$account->currency_id => (string) $account->current_balance]);

        // آخر عمليات الوردية بجانب النموذج: يرى الصرّاف ما سجّله للتو فلا
        // يكرّر عملية سهواً، ويطبع إيصالها دون مغادرة الصفحة.
        $recentTransactions = $shift->exchangeTransactions()
            ->with('currency')
            ->latest('id')
            ->limit(6)
            ->get()
            ->map(fn (ExchangeTransaction $transaction) => [
                'id' => $transaction->id,
                'created_at' => $transaction->created_at?->toIso8601String(),
                'type' => $transaction->type,
                'currency' => $transaction->currency?->only(['id', 'code']),
                'currency_tag' => $transaction->currency_tag,
                'amount' => (string) $transaction->amount,
                'rate' => (string) $transaction->rate,
                'total' => (string) $transaction->total,
                'customer_name' => $transaction->customer_name,
            ]);

        return Inertia::render('Transactions/Create', [
            'shift' => $shift,
            'currencies' => $currencies,
            'baseCurrency' => $baseCurrency?->only(['id', 'code', 'name', 'symbol', 'tag']),
            'balances' => $balances,
            'recentTransactions' => $recentTransactions,
            'transactionsCount' => $shift->exchangeTransactions()->count(),
            'canDeposit' => $request->user()->can('recordMovement', $shift),
        ]);
    }

    public function store(StoreExchangeTransactionRequest $request): RedirectResponse
    {
        $shift = $this->shiftService->getCurrentOpenShift($request->user());

        if (! $shift) {
            return redirect()->route('shifts.create')->with('error', 'يجب فتح ورديتك أولاً قبل تسجيل عمليات صرافة.');
        }

        $transaction = $this->exchangeService->recordTransaction($shift, $request->user(), $request->validated());
        $transaction->loadMissing('currency');

        $redirect = redirect()->route('transactions.create')->with('success', 'تم تسجيل العملية بنجاح.');

        /*
         * بعد كل عملية يُسأل الصرّاف عن طباعة الإيصال بدل أن تُفتح نافذة صامتة
         * قد يحجبها المتصفح. ومن فعّل الفتح التلقائي من الإعدادات يبقى سلوكه
         * كما كان: تُفتح النافذة دون سؤال.
         */
        if ($this->settings->bool('receipts.enabled', true)) {
            $redirect->with('receipt', [
                'url' => route('transactions.receipt', ['transaction' => $transaction->id, 'autoprint' => 1]),
                'auto' => $this->settings->bool('receipts.auto_open_after_transaction', false),
                'number' => $this->settings->string('receipts.number_prefix', 'TX-').$transaction->id,
                'summary' => $this->summaryFor($transaction),
            ]);
        }

        return $redirect;
    }

    /**
     * سطر مختصر يميّز العملية في نافذة السؤال، حتى لا يطبع الصرّاف إيصال
     * عملية وهو يظن أنه إيصال أخرى.
     */
    private function summaryFor(ExchangeTransaction $transaction): string
    {
        $verb = $transaction->type === ExchangeTransaction::TYPE_BUY ? 'شراء' : 'بيع';
        $tag = $transaction->currency_tag ? " ({$transaction->currency_tag})" : '';

        return "{$verb} {$transaction->amount} {$transaction->currency->code}{$tag}";
    }
}
