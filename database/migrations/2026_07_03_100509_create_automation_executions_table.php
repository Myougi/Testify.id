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
        Schema::create('automation_executions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('automation_run_id')->constrained('automation_runs')->cascadeOnDelete();
            $table->foreignId('test_case_id')->constrained('test_cases')->cascadeOnDelete();
            $table->foreignId('test_case_automation_id')->nullable()->constrained('test_case_automations')->nullOnDelete();
            $table->json('definition_snapshot');
            $table->string('status', 20)->default('Queued');
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('failure_type', 40)->nullable();
            $table->text('failure_message')->nullable();
            $table->unsignedSmallInteger('failed_step')->nullable();
            $table->text('console_excerpt')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['automation_run_id', 'test_case_id']);
            $table->index(['test_case_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });

        $this->enablePostgresRls('automation_executions');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('automation_executions');
    }

    private function enablePostgresRls(string $table): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("alter table {$table} enable row level security");
        }
    }
};
