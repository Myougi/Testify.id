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
        Schema::table('test_runs', function (Blueprint $table) {
            $table->timestamp('execution_started_at')->nullable()->after('status');
            $table->timestamp('execution_completed_at')->nullable()->after('execution_started_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('test_runs', function (Blueprint $table) {
            $table->dropColumn(['execution_started_at', 'execution_completed_at']);
        });
    }
};
