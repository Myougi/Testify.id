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
        Schema::create('automation_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kind', 20);
            $table->foreignId('test_run_id')->nullable()->constrained('test_runs')->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('environment', 32);
            $table->string('base_url', 2048);
            $table->unsignedInteger('attempt')->default(1);
            $table->string('provider', 40)->default('github_actions');
            $table->string('provider_run_id')->nullable();
            $table->string('provider_run_url', 2048)->nullable();
            $table->string('status', 20)->default('Queued');
            $table->text('failure_message')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['test_run_id', 'attempt']);
            $table->index(['status', 'created_at']);
            $table->index(['requested_by', 'created_at']);
        });

        $this->enablePostgresRls('automation_runs');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('automation_runs');
    }

    private function enablePostgresRls(string $table): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("alter table {$table} enable row level security");
        }
    }
};
