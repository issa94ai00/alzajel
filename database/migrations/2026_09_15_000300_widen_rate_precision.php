<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * أعمدة الأسعار كانت decimal(18,4)، وأربع خانات لا تكفي سعراً مقلوباً.
     *
     * عند تبديل العملة الأساس يصير سعر الأساس القديم مقلوب سعر الهدف: مع هدف
     * حول 134 يصبح السعر ≈ 0.00744، فيُقصّ إلى 0.0074 شراءً وبيعاً معاً —
     * يختفي الهامش ويصير ربح كل عملية صفراً. عشر خانات تحفظ الفرق.
     *
     * المبالغ (amount/total/profit/الأرصدة) تبقى على أربع خانات: هي نقود لا
     * نِسَب، وتوسيعها يغيّر معنى التدوير في الدفاتر دون حاجة.
     */
    public function up(): void
    {
        Schema::table('exchange_rates', function (Blueprint $table) {
            $table->decimal('buy_rate', 24, 10)->change();
            $table->decimal('sell_rate', 24, 10)->change();
        });

        Schema::table('exchange_transactions', function (Blueprint $table) {
            $table->decimal('rate', 24, 10)->change();
            $table->decimal('reference_buy_rate', 24, 10)->change();
            $table->decimal('reference_sell_rate', 24, 10)->change();
        });
    }

    public function down(): void
    {
        Schema::table('exchange_rates', function (Blueprint $table) {
            $table->decimal('buy_rate', 18, 4)->change();
            $table->decimal('sell_rate', 18, 4)->change();
        });

        Schema::table('exchange_transactions', function (Blueprint $table) {
            $table->decimal('rate', 18, 4)->change();
            $table->decimal('reference_buy_rate', 18, 4)->change();
            $table->decimal('reference_sell_rate', 18, 4)->change();
        });
    }
};
