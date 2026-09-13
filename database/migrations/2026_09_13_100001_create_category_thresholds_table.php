<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_thresholds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->unsignedInteger('normal_capacity')->default(0);
            $table->timestamps();

            $table->unique(['category_id', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_thresholds');
    }
};
