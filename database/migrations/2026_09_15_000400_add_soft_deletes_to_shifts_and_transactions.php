<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * حذف ناعم لسجلات العمليات والورديات.
 *
 * السجل المالي لا يُمحى: الحذف يُخفي السطر عن الشاشات والتقارير ويُبقيه في
 * قاعدة البيانات مع من حذفه ومتى ولماذا، فيبقى بالإمكان استعادته أو مراجعته.
 *
 * أعمدة التدقيق هنا تكرار مقصود لما يُسجَّل في activity_logs: السطر نفسه يحمل
 * قصّته، فلا يحتاج من يقرأ الجدول إلى مطابقته بسجل النشاط ليعرف سبب اختفائه.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->softDeletes();
            $table->foreignId('deleted_by')->nullable()->after('deleted_at')->constrained('users')->nullOnDelete();
            $table->string('deletion_reason', 500)->nullable()->after('deleted_by');
        });

        Schema::table('exchange_transactions', function (Blueprint $table) {
            $table->softDeletes();
            $table->foreignId('deleted_by')->nullable()->after('deleted_at')->constrained('users')->nullOnDelete();
            $table->string('deletion_reason', 500)->nullable()->after('deleted_by');

            /*
             * يميّز العملية التي اختفت تبعاً لحذف ورديتها عن عملية حُذفت وحدها.
             * بدونه لا تعرف استعادةُ الوردية أيَّ العمليات تُعيد، فتُحيي عمليةً
             * حذفها المحاسب عمداً قبل أسابيع.
             */
            $table->boolean('deleted_with_shift')->default(false)->after('deletion_reason');
        });
    }

    public function down(): void
    {
        Schema::table('exchange_transactions', function (Blueprint $table) {
            $table->dropForeign(['deleted_by']);
            $table->dropColumn(['deleted_at', 'deleted_by', 'deletion_reason', 'deleted_with_shift']);
        });

        Schema::table('shifts', function (Blueprint $table) {
            $table->dropForeign(['deleted_by']);
            $table->dropColumn(['deleted_at', 'deleted_by', 'deletion_reason']);
        });
    }
};
