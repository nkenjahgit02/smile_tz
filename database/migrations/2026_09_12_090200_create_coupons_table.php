<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('description')->nullable();
            $table->enum('type', ['percentage', 'fixed'])->default('percentage');
            $table->decimal('value', 14, 2)->comment('percentage or fixed amount');
            $table->decimal('min_spend', 14, 2)->default(0);
            $table->decimal('max_discount', 14, 2)->nullable()->comment('cap for percentage coupons');
            $table->integer('usage_limit')->nullable();
            $table->integer('used_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_to')->nullable();
            $table->timestamps();
        });

        Schema::create('sale_coupons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->cascadeOnDelete();
            $table->foreignId('coupon_id')->constrained();
            $table->decimal('discount_amount', 14, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_coupons');
        Schema::dropIfExists('coupons');
    }
};
