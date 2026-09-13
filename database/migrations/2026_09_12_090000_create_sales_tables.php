<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('customer_id')->nullable()->constrained();
            $table->foreignId('shift_id')->nullable()->constrained();
            $table->string('sale_no')->unique();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('tax_total', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->decimal('amount_paid', 14, 2)->default(0);
            $table->decimal('change_amount', 14, 2)->default(0);
            $table->enum('status', ['active', 'voided', 'returned'])->default('active');
            $table->enum('payment_status', ['unpaid', 'partial', 'paid'])->default('unpaid');
            $table->string('receipt_no')->nullable()->unique();
            $table->foreignId('voided_by')->nullable()->constrained('users');
            $table->text('void_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('product_variant_id')->nullable()->constrained();
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('original_unit_price', 14, 2)->comment('catalog price at time of sale');
            $table->decimal('unit_price', 14, 2)->comment('actual charged price');
            $table->decimal('discount', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2)->comment('unit_price * quantity - discount');
            $table->decimal('cost_price', 14, 2)->default(0)->comment('COGS from FIFO');
            $table->decimal('profit', 14, 2)->default(0);
            $table->boolean('is_loss')->default(false);
            $table->string('loss_reason')->nullable();
            $table->boolean('price_overridden')->default(false);
            $table->string('price_override_reason')->nullable();
            $table->foreignId('price_override_approved_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->cascadeOnDelete();
            $table->enum('method', ['cash', 'card', 'mobile', 'bank_transfer', 'loyalty']);
            $table->decimal('amount', 14, 2);
            $table->string('reference')->nullable()->comment('card auth code, mobile tx id etc.');
            $table->foreignId('received_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
    }
};
