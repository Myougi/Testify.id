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
        Schema::create('test_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_plan_id')
                ->constrained('test_plans')
                ->cascadeOnDelete();
            $table->string('name');
            $table->enum('environment', ['Development', 'SIT', 'Staging', 'UAT', 'Production']);
            $table->enum('priority', ['Low', 'Medium', 'High', 'Urgent'])->default('Medium');
            $table->text('description')->nullable();
            $table->string('tester_name');
            $table->enum('status', ['New', 'In Progress', 'Completed'])
                ->default('New');
            $table->timestamps();

            $table->index(['test_plan_id', 'status']);
            $table->index(['environment', 'priority']);
        });

        Schema::create('test_case_test_run', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_run_id')
                ->constrained('test_runs')
                ->cascadeOnDelete();
            $table->foreignId('test_case_id')
                ->constrained('test_cases')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['test_run_id', 'test_case_id']);
            $table->index(['test_case_id', 'test_run_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('test_case_test_run');
        Schema::dropIfExists('test_runs');
    }
};
