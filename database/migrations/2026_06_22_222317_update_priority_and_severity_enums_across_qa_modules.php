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
        if (! Schema::hasColumn('test_plans', 'priority')) {
            $this->addTestPlanPriorityColumn();
        }

        if (! $this->usesMysql()) {
            return;
        }

        // Legacy MySQL enum transitions are only needed for environments
        // that still contain the old values from earlier revisions.
        DB::statement("ALTER TABLE test_cases MODIFY priority ENUM('P1', 'P2', 'P3', 'Low', 'Medium', 'High', 'Urgent') NOT NULL");
        DB::table('test_cases')->where('priority', 'P1')->update(['priority' => 'High']);
        DB::table('test_cases')->where('priority', 'P2')->update(['priority' => 'Medium']);
        DB::table('test_cases')->where('priority', 'P3')->update(['priority' => 'Low']);
        DB::statement("ALTER TABLE test_cases MODIFY priority ENUM('Low', 'Medium', 'High', 'Urgent') NOT NULL");

        DB::statement("ALTER TABLE test_runs MODIFY priority ENUM('Low', 'Medium', 'High', 'Urgent') NOT NULL DEFAULT 'Medium'");

        DB::statement("ALTER TABLE issues MODIFY priority ENUM('Low', 'Medium', 'High', 'Urgent') NOT NULL DEFAULT 'Medium'");
        DB::statement("ALTER TABLE issues MODIFY severity ENUM('Critical', 'Major', 'Minor', 'Low', 'Medium', 'High') NOT NULL DEFAULT 'Medium'");
        DB::table('issues')->where('severity', 'Major')->update(['severity' => 'High']);
        DB::table('issues')->where('severity', 'Minor')->update(['severity' => 'Low']);
        DB::statement("ALTER TABLE issues MODIFY severity ENUM('Low', 'Medium', 'High', 'Critical') NOT NULL DEFAULT 'Medium'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if ($this->usesMysql()) {
            // Re-apply the legacy MySQL enum values only for rollback on
            // databases that originally ran the historic transition.
            DB::table('test_cases')->where('priority', 'Low')->update(['priority' => 'P3']);
            DB::table('test_cases')->where('priority', 'Medium')->update(['priority' => 'P2']);
            DB::table('test_cases')->whereIn('priority', ['High', 'Urgent'])->update(['priority' => 'P1']);
            DB::statement("ALTER TABLE test_cases MODIFY priority ENUM('P1', 'P2', 'P3') NOT NULL");

            DB::table('test_runs')->where('priority', 'Urgent')->update(['priority' => 'High']);
            DB::statement("ALTER TABLE test_runs MODIFY priority ENUM('Low', 'Medium', 'High') NOT NULL DEFAULT 'Medium'");

            DB::table('issues')->where('priority', 'Urgent')->update(['priority' => 'High']);
            DB::statement("ALTER TABLE issues MODIFY priority ENUM('Low', 'Medium', 'High') NOT NULL DEFAULT 'Medium'");

            DB::table('issues')->where('severity', 'Low')->update(['severity' => 'Minor']);
            DB::table('issues')->whereIn('severity', ['Medium', 'High'])->update(['severity' => 'Major']);
            DB::statement("ALTER TABLE issues MODIFY severity ENUM('Critical', 'Major', 'Minor') NOT NULL DEFAULT 'Major'");
        }

        if (Schema::hasColumn('test_plans', 'priority')) {
            Schema::table('test_plans', function (Blueprint $table): void {
                $table->dropColumn('priority');
            });
        }
    }

    private function usesMysql(): bool
    {
        return in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);
    }

    private function addTestPlanPriorityColumn(): void
    {
        Schema::table('test_plans', function (Blueprint $table): void {
            $column = $table
                ->enum('priority', ['Low', 'Medium', 'High', 'Urgent'])
                ->default('Medium');

            if ($this->usesMysql()) {
                $column->after('status');
            }
        });
    }
};
