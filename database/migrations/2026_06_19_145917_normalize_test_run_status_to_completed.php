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
        DB::table('test_runs')
            ->whereIn('status', ['Under Review', 'Rejected', 'Done'])
            ->update([
                'status' => 'Completed',
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('test_runs')
            ->where('status', 'Completed')
            ->update([
                'status' => 'Done',
            ]);
    }
};
