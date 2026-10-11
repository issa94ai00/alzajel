<?php

namespace App\Services;

use App\Models\ExchangeTransaction;
use App\Models\ReceiptTemplate;
use App\Models\Shift;
use App\Support\SettingsRepository;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * يبني بيانات الإيصال حسب القالب المختار. نفس البيانات تُستخدم للطباعة من الويب
 * ولإرجاع JSON للتطبيق المحمول، والمدير يتحكم بمحتواها من صفحة الإعدادات.
 */
class ReceiptService
{
    public function __construct(private readonly SettingsRepository $settings) {}

    public function enabled(): bool
    {
        return $this->settings->bool('receipts.enabled', true);
    }

    /**
     * @return Collection<int, ReceiptTemplate>
     */
    public function templatesFor(string $subject): Collection
    {
        return ReceiptTemplate::active()->forSubject($subject)->orderByDesc('is_default')->orderBy('name')->get();
    }

    public function resolveTemplate(string $subject, ?string $key = null): ReceiptTemplate
    {
        $templates = $this->templatesFor($subject);

        if ($key) {
            $chosen = $templates->firstWhere('key', $key);

            if ($chosen) {
                return $chosen;
            }
        }

        $settingKey = $subject === ReceiptTemplate::SUBJECT_SHIFT
            ? 'receipts.default_shift_template'
            : 'receipts.default_transaction_template';

        $preferred = $this->settings->string($settingKey, '');

        return $templates->firstWhere('key', $preferred)
            ?? $templates->firstWhere('is_default', true)
            ?? $templates->first()
            ?? $this->fallbackTemplate($subject);
    }

    /**
     * @return array<string, mixed>
     */
    public function forTransaction(ExchangeTransaction $transaction, ?string $templateKey = null): array
    {
        $template = $this->resolveTemplate(ReceiptTemplate::SUBJECT_TRANSACTION, $templateKey);
        $transaction->loadMissing(['currency', 'counterCurrency', 'user', 'shift']);

        $isBuy = $transaction->type === ExchangeTransaction::TYPE_BUY;

        $lines = [
            ['label' => 'نوع العملية', 'value' => $isBuy ? 'شراء عملة' : 'بيع عملة', 'emphasis' => true],
            ['label' => 'العملة', 'value' => "{$transaction->currency->code} — {$transaction->currency->name}"],
        ];

        // الفئة تُطبع كما سُجّلت وقت العملية، لا كما هي في العملة الآن.
        if ($transaction->currency_tag) {
            $lines[] = ['label' => 'الفئة', 'value' => $transaction->currency_tag];
        }

        $lines[] = ['label' => 'المبلغ', 'value' => $this->number($transaction->amount), 'suffix' => $transaction->currency->code, 'emphasis' => true];

        if ($this->settings->bool('receipts.show_rate', true)) {
            $lines[] = ['label' => 'سعر الصرف', 'value' => $this->rateNumber($transaction->rate)];
        }

        $lines[] = [
            'label' => $isBuy ? 'المدفوع للعميل' : 'المستلم من العميل',
            'value' => $this->number($transaction->total),
            'suffix' => $transaction->counterCurrency->code,
            'emphasis' => true,
        ];

        if ($this->settings->bool('receipts.show_customer', true) && $transaction->customer_name) {
            $lines[] = ['label' => 'العميل', 'value' => $transaction->customer_name];

            if ($transaction->customer_phone) {
                $lines[] = ['label' => 'الهاتف', 'value' => $transaction->customer_phone];
            }
        }

        if ($this->settings->bool('receipts.show_operator', true)) {
            $lines[] = ['label' => 'الموظف', 'value' => $transaction->user->name];
        }

        if ($this->settings->bool('receipts.show_notes', true) && $transaction->notes) {
            $lines[] = ['label' => 'ملاحظات', 'value' => $transaction->notes];
        }

        if ($this->settings->bool('receipts.show_profit', false)) {
            $lines[] = ['label' => 'الربح', 'value' => $this->number($transaction->profit)];
        }

        return $this->payload($template, [
            'title' => $template->option('title', 'إيصال عملية صرف'),
            'number' => $this->settings->string('receipts.number_prefix', 'TX-').$transaction->id,
            'issued_at' => $transaction->created_at->format('Y-m-d H:i'),
            'reference' => [
                'shift_id' => $transaction->shift_id,
                'transaction_id' => $transaction->id,
            ],
            'lines' => $lines,
            'tables' => [],
            'mentions_new_lira' => $this->mentionsNewLira([
                $transaction->currency->code,
                $transaction->counterCurrency->code,
            ]),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function forShift(Shift $shift, ?string $templateKey = null): array
    {
        $template = $this->resolveTemplate(ReceiptTemplate::SUBJECT_SHIFT, $templateKey);
        $shift->loadMissing(['openedBy', 'closedBy', 'shiftAccounts.currency']);

        $transactions = $shift->exchangeTransactions()->get();

        $lines = [
            ['label' => 'رقم الوردية', 'value' => '#'.$shift->id, 'emphasis' => true],
            ['label' => 'تاريخ العمل', 'value' => $shift->business_date->toDateString()],
            ['label' => 'الحالة', 'value' => $shift->isOpen() ? 'مفتوحة' : 'مغلقة'],
            ['label' => 'فتحها', 'value' => $shift->openedBy?->name ?? '—'],
            ['label' => 'وقت الفتح', 'value' => $shift->opened_at?->format('Y-m-d H:i') ?? '—'],
        ];

        if (! $shift->isOpen()) {
            $lines[] = ['label' => 'أغلقها', 'value' => $shift->closedBy?->name ?? '—'];
            $lines[] = ['label' => 'وقت الإغلاق', 'value' => $shift->closed_at?->format('Y-m-d H:i') ?? '—'];
        }

        $lines[] = ['label' => 'عدد العمليات', 'value' => (string) $transactions->count(), 'emphasis' => true];

        if ($this->settings->bool('receipts.show_profit', false)) {
            $lines[] = [
                'label' => 'صافي الربح',
                'value' => $this->number($transactions->reduce(fn ($carry, $t) => bcadd($carry, (string) $t->profit, 4), '0')),
            ];
        }

        $accounts = $shift->shiftAccounts->map(fn ($account) => [
            $account->currency->code,
            $this->number($account->opening_balance),
            $this->number($account->current_balance),
            $account->closing_balance !== null ? $this->number($account->closing_balance) : '—',
        ])->all();

        return $this->payload($template, [
            'title' => $template->option('title', 'كشف وردية'),
            'number' => 'SH-'.$shift->id,
            'issued_at' => now()->format('Y-m-d H:i'),
            'reference' => ['shift_id' => $shift->id],
            'lines' => $lines,
            'tables' => [[
                'title' => 'أرصدة العملات',
                'headers' => ['العملة', 'افتتاحي', 'حالي', 'إغلاق'],
                'rows' => $accounts,
            ]],
            'mentions_new_lira' => $this->mentionsNewLira(
                $shift->shiftAccounts->pluck('currency.code')->all()
            ),
        ]);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    /** رمز العملة التي أُعيدت تسميتها بحذف صفرين. */
    private const NEW_LIRA_CODE = 'SYP';

    /**
     * سطر يوضّح للعميل أن مبالغ الليرة بالعملة الجديدة — يُطبع فقط
     * على الإيصالات التي تذكر الليرة فعلاً.
     */
    private const NEW_LIRA_NOTE = 'مبالغ الليرة السورية بالعملة الجديدة (حُذف صفران). 100 ل.س قديمة = 1 ل.س ج';

    /**
     * @param  array<int, string|null>  $codes
     */
    private function mentionsNewLira(array $codes): bool
    {
        return in_array(self::NEW_LIRA_CODE, array_filter($codes), true);
    }

    private function payload(ReceiptTemplate $template, array $body): array
    {
        $mentionsNewLira = (bool) ($body['mentions_new_lira'] ?? false);
        unset($body['mentions_new_lira']);

        return array_merge($body, [
            'currency_note' => $mentionsNewLira && $this->settings->bool('currency.new_lira_notice', true)
                ? self::NEW_LIRA_NOTE
                : null,
            'template' => [
                'key' => $template->key,
                'name' => $template->name,
                'subject' => $template->subject,
                'paper' => $template->paper,
                'paper_spec' => $template->paperSpec(),
                'copies' => max(1, $template->copies),
                'is_thermal' => $template->isThermal(),
                'options' => $template->options ?? [],
            ],
            'company' => [
                'name' => $this->settings->string('company.name', config('app.name')),
                'branch' => $this->settings->string('company.branch'),
                'phone' => $this->settings->string('company.phone'),
                'address' => $this->settings->string('company.address'),
                'tax_number' => $this->settings->string('company.tax_number'),
                'logo_text' => $this->settings->string('company.logo_text'),
                'show_logo' => (bool) $template->option('show_logo', true),
            ],
            'header_text' => $template->option('header_text', ''),
            'footer_text' => $template->option('footer_text') ?: $this->settings->string('receipts.footer_note', ''),
            'show_signature' => $this->settings->bool('receipts.show_signature', false),
        ]);
    }

    /**
     * قالب احتياطي حين لا تكون القوالب مزروعة بعد في قاعدة البيانات.
     */
    private function fallbackTemplate(string $subject): ReceiptTemplate
    {
        $defaults = collect(config('sahli.receipt_templates', []))
            ->firstWhere('subject', $subject);

        if (! $defaults) {
            throw new RuntimeException("لا يوجد قالب إيصال متاح للنوع: {$subject}");
        }

        return new ReceiptTemplate($defaults);
    }

    /**
     * السعر يُطبع بعدد الخانات المعتاد لسهولة القراءة. الاستثناء هو السعر
     * الصغير جداً الناتج عن قلب سعر عملة قوية: تقريبه يطبعه "0.01" فيضيع
     * الفرق بين الشراء والبيع، فتبقى خاناته المعنوية.
     */
    private function rateNumber(mixed $value): string
    {
        $rate = (float) $value;
        $decimals = max(0, min(6, $this->settings->int('format.decimals', 2)));

        if ($rate != 0.0 && abs($rate) < 10 ** (1 - $decimals)) {
            $separator = $this->settings->bool('format.thousands_separator', true) ? ',' : '';
            // ست خانات معنوية تكفي لإظهار الفرق مهما صغر السعر.
            $significant = max(0, 5 - (int) floor(log10(abs($rate))));

            return rtrim(rtrim(number_format($rate, min($significant, 12), '.', $separator), '0'), '.');
        }

        return $this->number($value);
    }

    private function number(mixed $value): string
    {
        $decimals = max(0, min(6, $this->settings->int('format.decimals', 2)));
        $formatted = number_format((float) $value, $decimals, '.', $this->settings->bool('format.thousands_separator', true) ? ',' : '');

        return $formatted;
    }
}
