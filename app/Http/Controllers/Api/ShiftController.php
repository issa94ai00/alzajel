<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreShiftRequest;
use App\Http\Resources\CurrencyResource;
use App\Http\Resources\ShiftResource;
use App\Models\Currency;
use App\Models\Shift;
use App\Services\ReportService;
use App\Services\ShiftService;
use App\Support\Permissions;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    public function __construct(
        private readonly ShiftService $shiftService,
        private readonly ReportService $reportService,
    ) {}

    public function index(Request $request): array
    {
        $shifts = Shift::with(['openedBy', 'closedBy'])
            ->unless(
                $request->user()->can(Permissions::SHIFTS_VIEW_ANY),
                fn ($q) => $q->where('opened_by', $request->user()->id)
            )
            ->latest('opened_at')
            ->paginate(20);

        return [
            'data' => ShiftResource::collection($shifts->items()),
            'meta' => [
                'current_page' => $shifts->currentPage(),
                'last_page' => $shifts->lastPage(),
                'total' => $shifts->total(),
            ],
        ];
    }

    public function openFormData(Request $request): array
    {
        $this->authorize('create', Shift::class);

        $currentShift = $this->shiftService->getCurrentOpenShift($request->user());

        return [
            'currencies' => CurrencyResource::collection(
                Currency::where('is_active', true)->get()
            ),
            'current_shift_open' => (bool) $currentShift,
            'can_close_current' => $currentShift ? $request->user()->can('close', $currentShift) : false,
        ];
    }

    public function store(StoreShiftRequest $request): ShiftResource|array
    {
        $currentShift = $this->shiftService->getCurrentOpenShift($request->user());

        if ($currentShift) {
            $this->authorize('close', $currentShift);

            $shift = $this->shiftService->closeCurrentAndOpenNew($request->user(), $request->validated('opening_balances', []));

            return new ShiftResource($shift->load(['openedBy', 'closedBy']));
        }

        $shift = $this->shiftService->openShift($request->user(), $request->validated('opening_balances', []));

        return new ShiftResource($shift->load(['openedBy', 'closedBy']));
    }

    public function show(Shift $shift): array
    {
        $this->authorize('view', $shift);

        return [
            'shift' => new ShiftResource($shift->load(['openedBy', 'closedBy', 'shiftAccounts.currency'])),
            'balances' => $this->reportService->currentBalances($shift),
            'profit_loss' => $this->reportService->profitLossForShift($shift),
        ];
    }

    public function close(Request $request, Shift $shift): ShiftResource
    {
        $this->authorize('close', $shift);

        $shift = $this->shiftService->closeShift($shift, $request->user());

        return new ShiftResource($shift->load(['openedBy', 'closedBy']));
    }
}
