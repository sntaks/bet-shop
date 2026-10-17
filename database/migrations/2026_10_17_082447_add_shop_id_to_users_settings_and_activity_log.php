<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('shop_id')->nullable()->after('id')->constrained()->restrictOnDelete();
        });

        // Settings were one row for the single shop; now each shop has its own row.
        DB::table('shop_settings')->delete();

        Schema::table('shop_settings', function (Blueprint $table) {
            $table->foreignId('shop_id')->after('id')->unique()->constrained()->cascadeOnDelete();
        });

        Schema::table('activity_log', function (Blueprint $table) {
            $table->foreignId('shop_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->index(['shop_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropIndex(['shop_id', 'created_at']);
            $table->dropConstrainedForeignId('shop_id');
        });

        Schema::table('shop_settings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shop_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shop_id');
        });
    }
};
