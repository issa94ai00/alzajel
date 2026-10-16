<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * رمز الفئة: سلسلة مميّزة تفرّق بين فئات العملة الواحدة (مثلاً USD-100
     * لفئة المئة دولار و USD-50 لفئة الخمسين)، ولكل فئة سعرها ورصيدها.
     *
     * ولأن الفئات صفوف مستقلة بنفس الرمز، لم يعد code وحده صالحاً كقيد فريد؛
     * الفرادة صارت على tag، وتكامل code+tag يتحقق في طبقة التحقق.
     */
    public function up(): void
    {
        Schema::table('currencies', function (Blueprint $table) {
            $table->string('tag', 50)->nullable()->unique()->after('symbol');
        });

        Schema::table('currencies', function (Blueprint $table) {
            $table->dropUnique('currencies_code_unique');
            $table->index('code');
        });
    }

    /**
     * ملاحظة: الرجوع يفشل إن وُجدت أكثر من فئة بنفس الرمز — احذف الفئات
     * الزائدة أولاً، فاستعادة القيد الفريد على code تتطلب رموزاً غير مكرّرة.
     */
    public function down(): void
    {
        Schema::table('currencies', function (Blueprint $table) {
            $table->dropIndex(['code']);
            $table->unique('code');
        });

        Schema::table('currencies', function (Blueprint $table) {
            $table->dropUnique(['tag']);
        });

        Schema::table('currencies', function (Blueprint $table) {
            $table->dropColumn('tag');
        });
    }
};
