<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_rewards', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('points_cost');
            $table->enum('type', ['points', 'percentage', 'fixed'])->default('points');
            $table->decimal('value', 12, 2)->default(0);
            $table->enum('min_tier', ['bronze', 'silver', 'gold', 'platinum'])->default('bronze');
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_rewards');
    }
};
