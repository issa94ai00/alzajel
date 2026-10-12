<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeleteRecordRequest;
use App\Http\Requests\StoreShiftRequest;
use App\Models\Currency;
use App\Models\Shift;
use App\Models\ShiftAccount;
use App\Models\ShiftMovement;
use App\Models\User;
use App\Services\CashMovementService;
use App\Services\ReportService;
use App\Services\ShiftService;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShiftController extends Controller
{
    public function __construct(
        private readonly ShiftService $shiftService,
        private readonly ReportService $reportService,
        private readonly CashMovementService $cashMovements,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Shift::class);

        $user = $request->user();
        $canSeeAll = $user->can(Permissions::SHIFTS_VIEW_ANY);
        $canSeeTrashed = $user->can('viewTrashed', Shift::class);

        $trashed = $request->string('trashed')->toString();

        // سلّة المحذوفات محجوبة عمّن لا يملك صلاحية الحذف.
        if (! $canSeeTrashed || ! in_array($trashed, ['only', 'with'], true)) {
            $trashed = '';
        }

        $status = $request->string('status')->toString();
        $search = $request->string('search')->toString();

        $shifts = Shift::with(['openedBy:id,name', 'closedBy:id,name', 'deletedBy:id,name'])
            ->withCount([
                'exchangeTransactions',
                // الوردية المحذوفة حُذفت عملياتها معها، فالعدّ الحيّ يظهر صفراً
                // ولا يُخبر المراجع بما بداخلها.
                'exchangeTransactions as all_transactions_count' => fn ($q) => $q->withTrashed(),
            ])
            ->trashedFilter($trashed ?: null)
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($search, function ($q, $search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('business_date', 'like', "%{$search}%")
                        ->orWhereHas('openedBy', fn ($u) => $u->where('name', 'like', "%{$search}%"));
                });
            })
            // بدون صلاحية الإشراف يرى المستخدم ورديّاته فقط.
            ->unless($canSeeAll, fn ($q) => $q->where('opened_by', $user->id))
            ->latest('opened_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Shift $shift) => $this->summarize($shift, $user));

        return Inertia::render('Shifts/Index', [
            'shifts' => $shifts,
            'canSeeAll' => $canSeeAll,
            // لوحة الورديات المفتوحة الآن — لمن يشرف على بقية الصرّافين.
            'openShifts' => $canSeeAll
                ? $this->shiftService->openShifts()->map(fn (Shift $shift) => [
                    'id' => $shift->id,
                    'user' => $shift->openedBy?->name,
                    'user_id' => $shift->opened_by,
                    'business_date' => $shift->business_date->toDateString(),
                    'opened_at' => $shift->opened_at?->toIso8601String(),
                    'can_close' => $request->user()->can('close', $shift),
                ])->values()
                : [],
            'myOpenShift' => $this->shiftService->getCurrentOpenShift($user)?->only(['id', 'business_date']),
            'canSeeTrashed' => $canSeeTrashed,
            'trashedCount' => $canSeeTrashed ? Shift::onlyTrashed()->count() : 0,
            'filters' => [
                'trashed' => $trashed,
                'status' => $status,
                'search' => $search,
            ],
        ]);
    }

    /**
     * سطر الوردية كما تراه الواجهة، ومعه ما يُسمح به عليه وأثر حذفه.
     *
     * @return array<string, mixed>
     */
    private function summarize(Shift $shift, User $viewer): array
    {
        return [
            'id' => $shift->id,
            'business_date' => $shift->business_date->toDateString(),
            'status' => $shift->status,
            'opened_at' => $shift->opened_at?->toIso8601String(),
            'closed_at' => $shift->closed_at?->toIso8601String(),
            'opened_by' => $shift->openedBy?->only(['id', 'name']),
            'closed_by' => $shift->closedBy?->only(['id', 'name']),
            'exchange_transactions_count' => $shift->trashed()
                ? $shift->all_transactions_count
                : $shift->exchange_transactions_count,
            'deleted_at' => $shift->deleted_at?->toIso8601String(),
            'deleted_by' => $shift->deletedBy?->name,
            'deletion_reason' => $shift->deletion_reason,
            'can' => [
                'delete' => $viewer->can('delete', $shift),
                'restore' => $viewer->can('restore', $shift),
            ],
        ];
    }

    /**
     * حذف ناعم لوردية مغلقة ومعها عملياتها — يبقى كل شيء قابلاً للاستعادة.
     */
    public function destroy(DeleteRecordRequest $request, Shift $shift): RedirectResponse
    {
        $this->authorize('delete', $shift);

        $this->shiftService->deleteShift($shift, $request->user(), $request->reason());

        return redirect()->route('shifts.index')->with('success', "تم حذف الوردية رقم {$shift->id} وعملياتها.");
    }

    public function restore(Request $request, Shift $shift): RedirectResponse
    {
        $this->authorize('restore', $shift);

        $this->shiftService->restoreShift($shift, $request->user());

        return back()->with('success', "تمت استعادة الوردية رقم {$shift->id} وعملياتها.");
    }

    public function create(Request $request): Response|RedirectResponse
    {
        $this->authorize('create', Shift::class);

        $user = $request->user();
        $currentShift = $this->shiftService->getCurrentOpenShift($user);

        // للمستخدم وردية مفتوحة ولا يملك صلاحية إغلاقها — لا شيء يفعله هنا.
        if ($currentShift && ! $user->can('close', $currentShift)) {
            return redirect()->route('dashboard');
        }

        $currencies = Currency::where('is_active', true)->orderByDesc('is_base')->orderBy('code')->get();

        /*
         * مصدر الأرصدة المقترحة يتبع الحالة:
         * - عند استبدال وردية مفتوحة، الصحيح هو رصيدها الحالي — أي ما في الدرج
         *   الآن بعد عمليات الوردية. الرجوع إلى إغلاق وردية أقدم كان يقترح رقماً
         *   يسبق كل عمليات الوردية الجارية، فيفتح الوردية الجديدة بفارق صامت.
         * - وبلا وردية مفتوحة، المرجع هو إغلاق آخر وردية للمستخدم.
         */
        $suggested = $currentShift
            ? $this->shiftService->currentBalances($currentShift)
            : $this->shiftService->previousClosingBalances($user);

        return Inertia::render('Shifts/Open', [
            'currencies' => $currencies,
            'currentShiftOpen' => (bool) $currentShift,
            'currentShift' => $currentShift?->only(['id', 'business_date', 'opened_at']),
            'previousBalances' => $suggested,
            'balanceSource' => $currentShift ? 'current_shift' : 'previous_shift',
            'strandedBalances' => $this->shiftService->strandedBalances($user, $currentShift),
        ]);
    }

    public function store(StoreShiftRequest $request): RedirectResponse
    {
        $user = $request->user();
        $currentShift = $this->shiftService->getCurrentOpenShift($user);

        if ($currentShift) {
            $this->authorize('close', $currentShift);

            $this->shiftService->closeCurrentAndOpenNew($user, $request->validated('opening_balances', []));

            return redirect()->route('dashboard')->with('success', 'تم إغلاق ورديتك السابقة وفتح وردية جديدة بنجاح.');
        }

        $this->shiftService->openShift($user, $request->validated('opening_balances', []));

        return redirect()->route('dashboard')->with('success', 'تم فتح الوردية بنجاح.');
    }

    public function show(Request $request, Shift $shift): Response
    {
        $this->authorize('view', $shift);

        $viewer = $request->user();
        $canRecordMovement = $viewer->can('recordMovement', $shift);

        /*
         * مجاميع الإيداع والسحب لكل عملة تُرسَل مع الأرصدة: بدونها يرى
         * المستخدم فرقاً بين رصيد الافتتاح والرصيد الحالي لا تفسّره عمليات
         * الصرافة وحدها، فيظنّه خطأ جرد.
         */
        $movementTotals = $this->cashMovements->totalsByCurrency($shift);

        $balances = $this->reportService->currentBalances($shift)
            ->map(function (array $row) use ($movementTotals) {
                $totals = $movementTotals[$row['currency']['id']] ?? ['deposits' => '0', 'withdrawals' => '0'];

                return $row + [
                    'deposits' => $totals['deposits'],
                    'withdrawals' => $totals['withdrawals'],
                ];
            });

        return Inertia::render('Shifts/Show', [
            'shift' => $shift->load(['openedBy:id,name', 'closedBy:id,name']),
            'balances' => $balances,
            'profitLoss' => $this->reportService->profitLossForShift($shift),
            'canClose' => $shift->isOpen() && $viewer->can('close', $shift),
            'isOwner' => $shift->opened_by === $viewer->id,
            'canRecordMovement' => $canRecordMovement,
            'movements' => $this->movementRows($shift, $viewer),
            // عملات الوردية فقط — لا معنى لإيداع عملة لا حساب لها هنا.
            'movementCurrencies' => $shift->shiftAccounts()
                ->with('currency:id,code,name')
                ->get()
                ->map(fn (ShiftAccount $a) => [
                    'id' => $a->currency_id,
                    'code' => $a->currency?->code,
                    'name' => $a->currency?->name,
                    'current_balance' => (string) $a->current_balance,
                ])
                ->values(),
        ]);
    }

    /**
     * حركات الوردية النقدية كما تراها الواجهة، ومعها المحذوف لمن يملك التراجع.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function movementRows(Shift $shift, User $viewer): \Illuminate\Support\Collection
    {
        $canSeeTrashed = $viewer->can(Permissions::SHIFTS_DEPOSIT);

        return $shift->movements()
            ->with(['currency:id,code,name', 'user:id,name', 'deletedBy:id,name'])
            ->trashedFilter($canSeeTrashed ? 'with' : null)
            ->latest('id')
            ->get()
            ->map(fn (ShiftMovement $m) => [
                'id' => $m->id,
                'type' => $m->type,
                'type_label' => $m->typeLabel(),
                'amount' => (string) $m->amount,
                'currency' => $m->currency?->only(['id', 'code', 'name']),
                'notes' => $m->notes,
                'user' => $m->user?->name,
                'created_at' => $m->created_at?->toIso8601String(),
                'deleted_at' => $m->deleted_at?->toIso8601String(),
                'deleted_by' => $m->deletedBy?->name,
                'deletion_reason' => $m->deletion_reason,
                'can' => [
                    'delete' => $viewer->can('delete', $m),
                    'restore' => $viewer->can('restore', $m),
                ],
            ]);
    }

    public function close(Request $request, Shift $shift): RedirectResponse
    {
        $this->authorize('close', $shift);

        $this->shiftService->closeShift($shift, $request->user());

        return redirect()->route('shifts.show', $shift)->with('success', 'تم إغلاق الوردية بنجاح.');
    }
}
