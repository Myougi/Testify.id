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
        foreach (['projects', 'test_repository_modules', 'test_plans', 'test_cases', 'test_runs', 'issues'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->unsignedBigInteger('api_version')->default(1);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['projects', 'test_repository_modules', 'test_plans', 'test_cases', 'test_runs', 'issues'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn('api_version');
            });
        }
    }
};
