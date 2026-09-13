<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipt_copies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained();
            $table->integer('copy_no')->default(1);
            $table->json('content_snapshot');
            $table->foreignId('printed_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_copies');
    }
};
