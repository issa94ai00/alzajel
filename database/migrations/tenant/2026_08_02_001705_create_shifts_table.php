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
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opened_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('status', 10)->default('open');
            $table->date('business_date');
            $table->dateTime('opened_at');
            $table->dateTime('closed_at')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('single_open_lock')->nullable()->virtualAs("IF(status = 'open', 1, NULL)");
            $table->unique('single_open_lock');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};
