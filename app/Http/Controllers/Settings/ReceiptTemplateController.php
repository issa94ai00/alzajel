<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReceiptTemplateRequest;
use App\Models\ReceiptTemplate;
use App\Support\SettingsRepository;
use Illuminate\Http\RedirectResponse;

class ReceiptTemplateController extends Controller
{
    public function __construct(private readonly SettingsRepository $settings) {}

    public function store(StoreReceiptTemplateRequest $request): RedirectResponse
    {
        $template = ReceiptTemplate::create($request->validated());

        $this->syncDefault($template);

        return back()->with('success', 'تمت إضافة قالب الإيصال.');
    }

    public function update(StoreReceiptTemplateRequest $request, ReceiptTemplate $receiptTemplate): RedirectResponse
    {
        $receiptTemplate->update($request->validated());

        $this->syncDefault($receiptTemplate);

        return back()->with('success', 'تم تحديث قالب الإيصال.');
    }

    public function destroy(ReceiptTemplate $receiptTemplate): RedirectResponse
    {
        $remaining = ReceiptTemplate::forSubject($receiptTemplate->subject)
            ->where('id', '!=', $receiptTemplate->id)
            ->count();

        if ($remaining === 0) {
            return back()->with('error', 'لا يمكن حذف آخر قالب لهذا النوع من الإيصالات.');
        }

        $receiptTemplate->delete();

        return back()->with('success', 'تم حذف قالب الإيصال.');
    }

    /**
     * قالب افتراضي واحد فقط لكل نوع، مع مزامنة مفتاح الإعداد المرتبط به.
     */
    private function syncDefault(ReceiptTemplate $template): void
    {
        if (! $template->is_default) {
            return;
        }

        ReceiptTemplate::forSubject($template->subject)
            ->where('id', '!=', $template->id)
            ->update(['is_default' => false]);

        $this->settings->set(
            $template->subject === ReceiptTemplate::SUBJECT_SHIFT
                ? 'receipts.default_shift_template'
                : 'receipts.default_transaction_template',
            $template->key,
        );
    }
}
