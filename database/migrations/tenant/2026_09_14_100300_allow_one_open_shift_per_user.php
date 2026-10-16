<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * كان القفل يسمح بوردية مفتوحة واحدة في النظام كله. مع تعدد المستخدمين يصبح
     * القفل لكل مستخدم: صندوق نقدي مفتوح واحد لكل صرّاف في الوقت نفسه.
     */
    public function up(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->dropUnique('shifts_single_open_lock_unique');
            $table->unique(['opened_by', 'single_open_lock'], 'shifts_user_open_lock_unique');
        });
    }

    public function down(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->dropUnique('shifts_user_open_lock_unique');
            $table->unique('single_open_lock', 'shifts_single_open_lock_unique');
        });
    }
};
