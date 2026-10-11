<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

/**
 * رمز الفئة سلسلة اختيارية تميّز فئات العملة الواحدة (USD-100، USD-50…).
 *
 * تُجمع هنا معالجته المشتركة بين الإضافة والتعديل: تطبيع الفراغ إلى null
 * حتى لا تتساوى "" مع "" فيُرفض إدخالان بلا فئة لأسباب خاطئة، وقاعدة
 * فرادة الرمز داخل الفئة الواحدة.
 */
trait NormalizesCurrencyTag
{
    protected function prepareForValidation(): void
    {
        $tag = trim((string) $this->input('tag', ''));

        $this->merge(['tag' => $tag === '' ? null : $tag]);
    }

    /**
     * رمز العملة فريد ضمن رمز الفئة نفسه: يُسمح بـ USD مرتين بفئتين
     * مختلفتين، ويُمنع USD مرتين بلا فئة أو بنفس الفئة.
     */
    protected function uniqueCodePerTag(): Unique
    {
        $tag = $this->input('tag');

        $rule = Rule::unique('currencies', 'code')->where(
            fn ($query) => $tag === null ? $query->whereNull('tag') : $query->where('tag', $tag)
        );

        $currency = $this->route('currency');

        return $currency ? $rule->ignore($currency) : $rule;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.unique' => 'هذا الرمز مستخدم بنفس رمز الفئة — أعطِ الفئة الجديدة رمزاً مميزاً.',
            'tag.unique' => 'رمز الفئة مستخدم لعملة أخرى — لكل فئة رمز مميز.',
        ];
    }
}
