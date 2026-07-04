<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('test_case_automations', function (Blueprint $table) {
            $table->string('approval_source', 32)->nullable()->after('approved_by');
            $table->timestamp('last_healed_at')->nullable()->after('approved_at');
        });

        Schema::table('automation_executions', function (Blueprint $table) {
            $table->string('failure_code', 60)->nullable()->after('failure_type');
        });

        Schema::create('automation_healing_attempts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('test_case_automation_id')->constrained('test_case_automations')->cascadeOnDelete();
            $table->foreignUuid('source_execution_id')->unique()->constrained('automation_executions')->cascadeOnDelete();
            $table->foreignUuid('revalidation_run_id')->nullable()->constrained('automation_runs')->nullOnDelete();
            $table->string('status', 24)->default('Healing');
            $table->unsignedSmallInteger('failed_step');
            $table->json('original_definition');
            $table->json('proposed_definition')->nullable();
            $table->text('rationale')->nullable();
            $table->unsignedTinyInteger('confidence')->nullable();
            $table->char('source_hash', 64);
            $table->text('failure_message')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['test_case_automation_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('alter table automation_healing_attempts enable row level security');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_healing_attempts');

        Schema::table('automation_executions', function (Blueprint $table) {
            $table->dropColumn('failure_code');
        });

        Schema::table('test_case_automations', function (Blueprint $table) {
            $table->dropColumn(['approval_source', 'last_healed_at']);
        });
    }
};
