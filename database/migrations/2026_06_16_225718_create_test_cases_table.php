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
        Schema::create('test_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')
                ->constrained('projects', 'project_id')
                ->cascadeOnDelete();
            $table->foreignId('module_id')
                ->constrained('test_repository_modules')
                ->restrictOnDelete();
            $table->string('case_key', 40);
            $table->string('title');
            $table->enum('priority', ['Low', 'Medium', 'High', 'Urgent']);
            $table->enum('automation_status', ['Manual', 'Automated']);
            $table->enum('test_behavior', ['Positive', 'Negative']);
            $table->enum('test_type', [
                'Acceptance',
                'Accessibility',
                'Compatibility',
                'Functional',
                'Integration',
                'Performance',
                'Regression',
                'UI/UX',
                'Security',
                'Sanity',
                'Smoke',
            ]);
            $table->text('preconditions')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'case_key']);
            $table->index(['project_id', 'module_id']);
            $table->index(['test_type', 'test_behavior']);
            $table->index('priority');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('test_cases');
    }
};
