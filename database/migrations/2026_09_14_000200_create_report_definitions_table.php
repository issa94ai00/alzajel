<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('builder', 50);
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->boolean('admin_only')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->json('options')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_definitions');
    }
};
