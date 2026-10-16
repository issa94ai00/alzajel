<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * رمز الفئة وقت تنفيذ العملية. يُنسخ على العملية ولا يُقرأ من العملة
     * وقت الطباعة، حتى يبقى الإيصال القديم معبّراً عمّا سُلّم فعلاً إذا
     * عُدّل رمز الفئة أو حُذفت لاحقاً.
     */
    public function up(): void
    {
        Schema::table('exchange_transactions', function (Blueprint $table) {
            $table->string('currency_tag', 50)->nullable()->after('counter_currency_id');
        });
    }

    public function down(): void
    {
        Schema::table('exchange_transactions', function (Blueprint $table) {
            $table->dropColumn('currency_tag');
        });
    }
};
