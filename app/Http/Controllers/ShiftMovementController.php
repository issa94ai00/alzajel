<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeleteRecordRequest;
use App\Http\Requests\StoreShiftMovementRequest;
use App\Models\Shift;
use App\Models\ShiftMovement;
use App\Services\CashMovementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ShiftMovementController extends Controller
{
    public function __construct(
        private readonly CashMovementService $movements,
    ) {}

    public function store(StoreShiftMovementRequest $request, Shift $shift): RedirectResponse
    {
        $this->authorize('recordMovement', $shift);

        $movement = $this->movements->record($shift, $request->user(), $request->validated());

        return back()->with(
            'success',
            "تم تسجيل {$movement->typeLabel()} بمبلغ {$movement->amount} {$movement->currency->code}."
        );
    }

    public function destroy(DeleteRecordRequest $request, ShiftMovement $movement): RedirectResponse
    {
        $this->authorize('delete', $movement);

        $this->movements->delete($movement, $request->user(), $request->reason());

        return back()->with('success', "تم حذف {$movement->typeLabel()} رقم {$movement->id} وعكس أثره على الرصيد.");
    }

    public function restore(Request $request, ShiftMovement $movement): RedirectResponse
    {
        $this->authorize('restore', $movement);

        $this->movements->restore($movement, $request->user());

        return back()->with('success', "تمت استعادة {$movement->typeLabel()} رقم {$movement->id}.");
    }
}
