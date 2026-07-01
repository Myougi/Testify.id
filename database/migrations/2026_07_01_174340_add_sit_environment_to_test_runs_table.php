<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! $this->usesMysql()) {
            return;
        }

        DB::statement("ALTER TABLE test_runs MODIFY environment ENUM('Development', 'SIT', 'Staging', 'UAT', 'Production') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! $this->usesMysql()) {
            return;
        }

        DB::table('test_runs')
            ->where('environment', 'SIT')
            ->update(['environment' => 'Staging']);

        DB::statement("ALTER TABLE test_runs MODIFY environment ENUM('Development', 'Staging', 'UAT', 'Production') NOT NULL");
    }

    private function usesMysql(): bool
    {
        return in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true);
    }
};
