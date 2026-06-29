<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('test_case_test_run')
            ->where('execution_status', 'Pending')
            ->update([
                'execution_status' => 'Not Run',
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('test_case_test_run')
            ->where('execution_status', 'Not Run')
            ->update([
                'execution_status' => 'Pending',
            ]);
    }
};
