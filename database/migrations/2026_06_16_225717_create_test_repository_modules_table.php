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
        Schema::create('test_repository_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')
                ->constrained('projects', 'project_id')
                ->cascadeOnDelete();
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('test_repository_modules')
                ->restrictOnDelete();
            $table->string('name');
            $table->string('key_prefix', 20);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['project_id', 'parent_id', 'sort_order']);
            $table->index(['project_id', 'key_prefix']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('test_repository_modules');
    }
};
