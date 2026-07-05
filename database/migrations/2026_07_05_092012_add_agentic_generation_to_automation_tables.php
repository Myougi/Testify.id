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
        Schema::table('automation_runs', function (Blueprint $table) {
            $table->text('goal_prompt')->nullable();
            $table->string('phase', 120)->nullable()->index();
            $table->string('progress_message', 500)->nullable();
            $table->string('generation_source', 40)->nullable();
            $table->string('model', 255)->nullable();
            $table->json('warnings')->nullable();
            $table->string('failure_code', 80)->nullable();
            $table->char('source_hash', 64)->nullable()->index();
            $table->foreignUuid('parent_run_id')->nullable()->constrained('automation_runs')->nullOnDelete();
        });

        Schema::table('automation_executions', function (Blueprint $table) {
            $table->json('definition_snapshot')->nullable()->change();
        });

        Schema::table('test_case_automations', function (Blueprint $table) {
            $table->string('generation_source', 40)->nullable();
            $table->foreignUuid('generation_run_id')->nullable()->constrained('automation_runs')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('test_case_automations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('generation_run_id');
            $table->dropColumn('generation_source');
        });

        DB::table('automation_runs')->where('kind', 'Generation')->delete();

        Schema::table('automation_executions', function (Blueprint $table) {
            $table->json('definition_snapshot')->nullable(false)->change();
        });

        Schema::table('automation_runs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_run_id');
            $table->dropIndex(['source_hash']);
            $table->dropIndex(['phase']);
            $table->dropColumn([
                'goal_prompt',
                'phase',
                'progress_message',
                'generation_source',
                'model',
                'warnings',
                'failure_code',
                'source_hash',
            ]);
        });
    }
};
