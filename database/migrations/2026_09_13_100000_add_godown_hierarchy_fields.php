<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->string('type', 20)->default('store')->index()->after('code');
            $table->foreignId('parent_id')->nullable()->after('id')
                ->constrained('branches')->nullOnDelete();
        });

        Schema::table('restock_requests', function (Blueprint $table) {
            $table->foreignId('source_branch_id')->nullable()->after('branch_id')
                ->constrained('branches')->nullOnDelete();
        });

        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->foreignId('restock_request_id')->nullable()->after('to_branch_id')
                ->constrained('restock_requests')->nullOnDelete();
            $table->index('restock_request_id');
        });
    }

    public function down(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->dropForeign(['restock_request_id']);
            $table->dropColumn('restock_request_id');
        });

        Schema::table('restock_requests', function (Blueprint $table) {
            $table->dropForeign(['source_branch_id']);
            $table->dropColumn('source_branch_id');
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn(['parent_id', 'type']);
        });
    }
};
