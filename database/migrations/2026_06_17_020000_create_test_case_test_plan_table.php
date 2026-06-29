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
        Schema::create('test_case_test_plan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_plan_id')
                ->constrained('test_plans')
                ->cascadeOnDelete();
            $table->foreignId('test_case_id')
                ->constrained('test_cases')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['test_plan_id', 'test_case_id']);
            $table->index(['test_case_id', 'test_plan_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('test_case_test_plan');
    }
};
