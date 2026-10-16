<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('exchange_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('type', 4);
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->foreignId('counter_currency_id')->constrained('currencies')->restrictOnDelete();
            $table->decimal('amount', 18, 4);
            $table->decimal('rate', 18, 4);
            $table->decimal('total', 18, 4);
            $table->decimal('reference_buy_rate', 18, 4);
            $table->decimal('reference_sell_rate', 18, 4);
            $table->decimal('profit', 18, 4);
            $table->string('customer_name')->nullable();
            $table->string('customer_phone')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['shift_id', 'currency_id']);
            $table->index('type');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exchange_transactions');
    }
};
