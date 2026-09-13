<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->decimal('credit_limit', 12, 2)->default(0)->after('notes');
            $table->decimal('credit_balance', 12, 2)->default(0)->after('credit_limit');
            $table->decimal('deposit_balance', 12, 2)->default(0)->after('credit_balance');
            $table->integer('loyalty_points')->default(0)->after('deposit_balance');
            $table->integer('lifetime_points')->default(0)->after('loyalty_points');
            $table->decimal('lifetime_spend', 12, 2)->default(0)->after('lifetime_points');
            $table->enum('loyalty_tier', ['bronze', 'silver', 'gold', 'platinum'])->default('bronze')->after('lifetime_spend');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'credit_limit', 'credit_balance', 'deposit_balance',
                'loyalty_points', 'lifetime_points', 'lifetime_spend', 'loyalty_tier',
            ]);
        });
    }
};
