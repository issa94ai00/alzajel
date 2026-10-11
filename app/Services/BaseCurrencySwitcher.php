<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * تبديل العملة الأساس وإعادة بناء جدول الأسعار حولها.
 *
 * كل سعر مخزّن يعني: كم وحدة من العملة الأساس مقابل وحدة واحدة من العملة
 * المتداولة. لذلك تغيير الأساس يُبطل معنى كل صف في الجدول، ولا يكفي قلب
 * العلم is_base.
 */
class BaseCurrencySwitcher
{
    /** الأسعار مُقوّمة فعلاً بالعملة الأساس الحالية، فتُحوَّل حسابياً. */
    public const MODE_CONVERT = 'convert';

    /** الأسعار مُدخلة أصلاً بالعملة الهدف، فيُصحَّح التصنيف دون تغيير الأرقام. */
    public const MODE_RELABEL = 'relabel';

    public const MODES = [self::MODE_CONVERT, self::MODE_RELABEL];

    /**
     * دقّة حساب الأسعار. السعر نسبة لا مبلغ، والمقلوب منه يبدأ بأصفار:
     * 1/134.25 = 0.0074487895… فأربع خانات تمحو الفرق بين الشراء والبيع
     * وتُصفّر الهامش. عشر خانات تطابق سعة العمود بعد توسيعه.
     */
    private const SCALE = 10;

    /**
     * ماذا سيصير جدول الأسعار لو صارت $target هي الأساس؟
     *
     * @return array{mode: string, target: array<string, mixed>, current_base: array<string, mixed>|null, rows: list<array<string, mixed>>, blockers: list<string>}
     */
    public function preview(Currency $target, string $mode): array
    {
        $this->assertMode($mode);

        $currentBase = Currency::where('is_base', true)->first();
        $targetRate = ExchangeRate::where('currency_id', $target->id)->first();

        $rows = [];

        foreach (Currency::orderBy('code')->get() as $currency) {
            $rate = ExchangeRate::where('currency_id', $currency->id)->first();

            $before = $rate
                ? ['buy' => (string) $rate->buy_rate, 'sell' => (string) $rate->sell_rate]
                : null;

            $rows[] = [
                'code' => $currency->code,
                'name' => $currency->name,
                'tag' => $currency->tag,
                'is_new_base' => $currency->id === $target->id,
                'was_base' => (bool) $currency->is_base,
                'before' => $before,
                'after' => $this->rateAfter($currency, $target, $currentBase, $targetRate, $rate, $mode),
            ];
        }

        return [
            'mode' => $mode,
            'target' => $target->only(['id', 'code', 'name', 'symbol', 'tag']),
            'current_base' => $currentBase?->only(['id', 'code', 'name', 'symbol', 'tag']),
            'rows' => $rows,
            'blockers' => $this->blockers($target, $mode, $targetRate),
        ];
    }

    /**
     * السعر الجديد لعملة واحدة، أو null إذا لم يعد لها صف (العملة الأساس).
     *
     * @return array{buy: string, sell: string}|null
     */
    private function rateAfter(
        Currency $currency,
        Currency $target,
        ?Currency $currentBase,
        ?ExchangeRate $targetRate,
        ?ExchangeRate $rate,
        string $mode,
    ): ?array {
        // العملة الأساس لا سعر لها مقابل نفسها.
        if ($currency->id === $target->id) {
            return null;
        }

        if ($mode === self::MODE_RELABEL) {
            // الأرقام صحيحة كما هي؛ يبقى الأساس القديم بلا سعر ليُدخله المدير.
            return $rate ? ['buy' => (string) $rate->buy_rate, 'sell' => (string) $rate->sell_rate] : null;
        }

        if (! $targetRate || $this->isZero((string) $targetRate->buy_rate) || $this->isZero((string) $targetRate->sell_rate)) {
            return null;
        }

        // الأساس القديم يصبح عملة متداولة: قيمته مقلوب سعر الهدف.
        if ($currentBase && $currency->id === $currentBase->id) {
            return [
                'buy' => bcdiv('1', (string) $targetRate->sell_rate, self::SCALE),
                'sell' => bcdiv('1', (string) $targetRate->buy_rate, self::SCALE),
            ];
        }

        if (! $rate) {
            return null;
        }

        // سعر تقاطعي محافظ: نشتري بأقل ما يمكن ونبيع بأعلى ما يمكن،
        // فيبقى الهامش في مصلحة الصرافة لا ضدها.
        return [
            'buy' => bcdiv((string) $rate->buy_rate, (string) $targetRate->sell_rate, self::SCALE),
            'sell' => bcdiv((string) $rate->sell_rate, (string) $targetRate->buy_rate, self::SCALE),
        ];
    }

    /**
     * أسباب تمنع التبديل الآن.
     *
     * @return list<string>
     */
    public function blockers(Currency $target, string $mode, ?ExchangeRate $targetRate = null): array
    {
        $blockers = [];

        if ($target->is_base) {
            $blockers[] = 'هذه العملة هي الأساس بالفعل.';
        }

        if (! $target->is_active) {
            $blockers[] = 'لا يمكن اعتماد عملة معطّلة كعملة أساس.';
        }

        // تغيير الأساس أثناء وردية مفتوحة يجعل عمليات الوردية الواحدة
        // مقوّمة بعملتين مختلفتين، وهو دفتر غير متماسك.
        $openShifts = Shift::open()->count();

        if ($openShifts > 0) {
            $blockers[] = "يوجد {$openShifts} وردية مفتوحة. أغلق كل الورديات قبل تغيير العملة الأساس.";
        }

        if ($mode === self::MODE_CONVERT) {
            $targetRate ??= ExchangeRate::where('currency_id', $target->id)->first();

            if (! $targetRate || $this->isZero((string) $targetRate->buy_rate) || $this->isZero((string) $targetRate->sell_rate)) {
                $blockers[] = 'التحويل الحسابي يحتاج سعر شراء وبيع صالحين للعملة الهدف.';
            }
        }

        return $blockers;
    }

    /**
     * تنفيذ التبديل داخل معاملة واحدة.
     */
    public function apply(Currency $target, string $mode, User $actor): void
    {
        $this->assertMode($mode);

        $blockers = $this->blockers($target, $mode);

        if ($blockers !== []) {
            throw ValidationException::withMessages(['base_currency' => $blockers]);
        }

        $preview = $this->preview($target, $mode);

        DB::transaction(function () use ($target, $preview, $actor) {
            foreach ($preview['rows'] as $row) {
                $currency = Currency::where('code', $row['code'])->firstOrFail();

                if ($row['after'] === null) {
                    ExchangeRate::where('currency_id', $currency->id)->delete();

                    continue;
                }

                ExchangeRate::updateOrCreate(
                    ['currency_id' => $currency->id],
                    [
                        'buy_rate' => $row['after']['buy'],
                        'sell_rate' => $row['after']['sell'],
                        'updated_by' => $actor->getKey(),
                    ],
                );
            }

            Currency::where('is_base', true)->update(['is_base' => false]);
            $target->forceFill(['is_base' => true, 'is_active' => true])->save();
        });
    }

    private function isZero(string $value): bool
    {
        return bccomp($value, '0', self::SCALE) === 0;
    }

    private function assertMode(string $mode): void
    {
        if (! in_array($mode, self::MODES, true)) {
            throw ValidationException::withMessages(['mode' => 'طريقة تحويل غير معروفة.']);
        }
    }
}
