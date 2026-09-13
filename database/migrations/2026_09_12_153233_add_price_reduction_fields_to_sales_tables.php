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
        Schema::table('sales', function (Blueprint $table) {
            $table->boolean('has_price_reduction')->default(false);
            $table->decimal('price_reduction_amount', 14, 2)->default(0);
            $table->string('price_reduction_reason')->nullable();
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->boolean('is_price_reduced')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['has_price_reduction', 'price_reduction_amount', 'price_reduction_reason']);
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn('is_price_reduced');
        });
    }
};
