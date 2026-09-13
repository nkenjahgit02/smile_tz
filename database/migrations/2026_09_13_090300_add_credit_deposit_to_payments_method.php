<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Module 5 adds credit-on-account and deposit/wallet sales to the checkout.
        Schema::table('payments', function (Blueprint $table) {
            $table->enum('method', ['cash', 'card', 'mobile', 'bank_transfer', 'loyalty', 'credit', 'deposit'])->change();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->enum('method', ['cash', 'card', 'mobile', 'bank_transfer', 'loyalty'])->change();
        });
    }
};
