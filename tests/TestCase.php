<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * حارس أمان: يمنع تشغيل الاختبارات على قاعدة بيانات الإنتاج.
     *
     * phpunit.xml يوجّه الاختبارات إلى sahlisoft_testing عبر <env>، لكن
     * `php artisan config:cache` يثبّت اسم قاعدة بيانات الإنتاج داخل الكونفيغ
     * المخزّن، فتتجاهل env() هذه القيمة وتصل الاختبارات — التي تستخدم
     * RefreshDatabase — إلى بيانات حقيقية. هنا نتوقف قبل أن يحدث ذلك.
     *
     * الحل عند ظهور الرسالة: `composer test` (يمسح الكونفيغ أولاً).
     */
    protected function setUp(): void
    {
        parent::setUp();

        $expected = env('DB_DATABASE');
        $actual = config('database.connections.'.config('database.default').'.database');

        if ($expected && $actual !== $expected) {
            $this->fail(
                "ABORTED: tests are pointed at database [{$actual}] but expected [{$expected}].\n".
                "A cached config is overriding phpunit.xml. Run: composer test"
            );
        }
    }
}
