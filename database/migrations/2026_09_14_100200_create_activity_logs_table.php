<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();

            // الفاعل — يبقى NULL لمحاولات الدخول الفاشلة ولمن حُذف حسابه لاحقاً.
            $table->foreignId('causer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('causer_name')->nullable();

            // الكيان المتأثر (مستخدم، وردية، دور...) كعلاقة متعددة الأشكال.
            $table->nullableMorphs('subject');

            $table->string('event', 60);
            $table->string('description');
            $table->json('properties')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();

            $table->timestamp('created_at')->nullable();

            $table->index('event');
            $table->index(['causer_id', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
