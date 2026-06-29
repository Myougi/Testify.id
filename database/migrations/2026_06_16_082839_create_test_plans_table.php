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
        Schema::create('test_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')
                ->constrained('projects', 'project_id')
                ->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('scope')->nullable();
            $table->text('strategy')->nullable();
            $table->text('criteria')->nullable();
            $table->enum('status', ['Draft', 'In Progress', 'Completed', 'Archived'])
                ->default('Draft');
            $table->enum('priority', ['Low', 'Medium', 'High', 'Urgent'])
                ->default('Medium');
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->text('release_version')->nullable();
            $table->string('tester')->nullable();
            $table->timestamps();

            $table->index(['status', 'priority', 'due_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('test_plans');
    }
};
