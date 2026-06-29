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
        Schema::create('issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')
                ->constrained('projects', 'project_id')
                ->cascadeOnDelete();
            $table->foreignId('test_plan_id')
                ->nullable()
                ->constrained('test_plans')
                ->nullOnDelete();
            $table->foreignId('test_run_id')
                ->nullable()
                ->constrained('test_runs')
                ->nullOnDelete();
            $table->text('description');
            $table->enum('priority', ['Low', 'Medium', 'High', 'Urgent'])->default('Medium');
            $table->enum('severity', ['Low', 'Medium', 'High', 'Critical'])->default('Medium');
            $table->enum('status', ['New', 'Open', 'Fixed', 'Closed'])->default('New');
            $table->string('reporter');
            $table->string('assignee')->nullable();
            $table->string('environment')->nullable();
            $table->string('defect_type')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index(['project_id', 'severity']);
            $table->index(['project_id', 'closed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('issues');
    }
};
