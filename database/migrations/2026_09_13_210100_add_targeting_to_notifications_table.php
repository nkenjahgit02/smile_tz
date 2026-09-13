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
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('category')->nullable()->index()->after('type');
            $table->string('target_role')->nullable()->index()->after('category');
            $table->foreignId('target_user_id')->nullable()->after('target_role')->constrained('users')->nullOnDelete();
            $table->foreignId('target_location_id')->nullable()->after('target_user_id')->constrained('branches')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('target_location_id');
            $table->dropConstrainedForeignId('target_user_id');
            $table->dropColumn(['target_role', 'category']);
        });
    }
};
