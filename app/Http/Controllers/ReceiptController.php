<?php

namespace App\Http\Controllers;

use App\Models\ExchangeTransaction;
use App\Models\ReceiptTemplate;
use App\Models\Shift;
use App\Services\ReceiptService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReceiptController extends Controller
{
    public function __construct(private readonly ReceiptService $receipts) {}

    public function transaction(Request $request, ExchangeTransaction $transaction): View
    {
        $this->authorize('view', $transaction);
        $this->assertEnabled();

        $receipt = $this->receipts->forTransaction($transaction, $request->string('template')->toString() ?: null);

        return view('receipts.print', [
            'receipt' => $receipt,
            'templates' => $this->receipts->templatesFor(ReceiptTemplate::SUBJECT_TRANSACTION),
            'backUrl' => route('transactions.index'),
            'autoPrint' => $request->boolean('autoprint'),
        ]);
    }

    public function shift(Request $request, Shift $shift): View
    {
        $this->authorize('view', $shift);
        $this->assertEnabled();

        $receipt = $this->receipts->forShift($shift, $request->string('template')->toString() ?: null);

        return view('receipts.print', [
            'receipt' => $receipt,
            'templates' => $this->receipts->templatesFor(ReceiptTemplate::SUBJECT_SHIFT),
            'backUrl' => route('shifts.show', $shift),
            'autoPrint' => $request->boolean('autoprint'),
        ]);
    }

    private function assertEnabled(): void
    {
        abort_unless($this->receipts->enabled(), 404, 'طباعة الإيصالات معطّلة من الإعدادات.');
    }
}
