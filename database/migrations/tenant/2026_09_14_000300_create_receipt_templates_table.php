<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipt_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('subject', 30)->index();
            $table->string('name');
            $table->string('paper', 10)->default('80mm');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedTinyInteger('copies')->default(1);
            $table->json('options')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_templates');
    }
};
