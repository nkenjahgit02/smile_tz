<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('branch_id')->constrained('branches');
            $table->foreignId('godown_id')->constrained('branches');
            $table->string('priority', 16)->default('normal');      // normal | urgent
            $table->date('required_date')->nullable();
            $table->string('status', 32)->default('draft')->index();
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('second_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('second_approved_at')->nullable();
            $table->boolean('requires_second_approval')->default(false);

            $table->foreignId('picker_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('picking_started_at')->nullable();
            $table->timestamp('picking_completed_at')->nullable();

            $table->string('dispatch_note_no')->nullable();
            $table->foreignId('dispatcher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dispatched_at')->nullable();

            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable();

            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason')->nullable();

            $table->string('idempotency_key')->nullable()->unique();
            $table->softDeletes();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('deletion_reason')->nullable();
            $table->timestamps();

            $table->index(['status', 'godown_id']);
            $table->index(['branch_id', 'created_at']);
        });

        Schema::create('branch_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('qty_requested', 12, 3);
            $table->decimal('qty_approved', 12, 3)->nullable();
            $table->decimal('qty_picked', 12, 3)->nullable();
            $table->decimal('qty_dispatched', 12, 3)->nullable();
            $table->decimal('qty_received', 12, 3)->nullable();
            $table->decimal('qty_damaged', 12, 3)->nullable();
            $table->decimal('qty_shortage', 12, 3)->nullable();
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('order_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_order_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->softDeletes();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('deletion_reason')->nullable();
            $table->timestamps();

            $table->index(['branch_order_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_history');
        Schema::dropIfExists('branch_order_items');
        Schema::dropIfExists('branch_orders');
    }
};
