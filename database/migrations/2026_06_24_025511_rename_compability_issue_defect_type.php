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
        DB::table('issues')
            ->where('defect_type', 'Compability')
            ->update(['defect_type' => 'Compatibility']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('issues')
            ->where('defect_type', 'Compatibility')
            ->update(['defect_type' => 'Compability']);
    }
};
