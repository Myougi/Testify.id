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
        Schema::table('test_case_test_run', function (Blueprint $table) {
            $table->string('execution_status', 20)
                ->default('Not Run')
                ->after('test_case_id')
                ->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('test_case_test_run', function (Blueprint $table) {
            $table->dropIndex(['execution_status']);
            $table->dropColumn('execution_status');
        });
    }
};
