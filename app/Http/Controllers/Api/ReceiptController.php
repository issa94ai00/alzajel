<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExchangeTransaction;
use App\Models\ReceiptTemplate;
use App\Models\Shift;
use App\Services\ReceiptService;
use Illuminate\Http\Request;

/**
 * بيانات الإيصالات بصيغة JSON للتطبيق المحمول — نفس المحتوى المطبوع من الويب.
 */
class ReceiptController extends Controller
{
    public function __construct(private readonly ReceiptService $receipts) {}

    public function templates(Request $request): array
    {
        $subject = $request->string('subject')->toString() ?: ReceiptTemplate::SUBJECT_TRANSACTION;

        abort_unless(in_array($subject, [ReceiptTemplate::SUBJECT_TRANSACTION, ReceiptTemplate::SUBJECT_SHIFT], true), 422);

        return [
            'enabled' => $this->receipts->enabled(),
            'templates' => $this->receipts->templatesFor($subject),
        ];
    }

    public function transaction(Request $request, ExchangeTransaction $transaction): array
    {
        $this->authorize('view', $transaction);
        $this->assertEnabled();

        return $this->receipts->forTransaction($transaction, $request->string('template')->toString() ?: null);
    }

    public function shift(Request $request, Shift $shift): array
    {
        $this->authorize('view', $shift);
        $this->assertEnabled();

        return $this->receipts->forShift($shift, $request->string('template')->toString() ?: null);
    }

    private function assertEnabled(): void
    {
        abort_unless($this->receipts->enabled(), 404, 'طباعة الإيصالات معطّلة من الإعدادات.');
    }
}
