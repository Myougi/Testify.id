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
        Schema::create('project_environments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')
                ->constrained('projects', 'project_id')
                ->cascadeOnDelete();
            $table->string('environment', 32);
            $table->string('base_url', 2048);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(['project_id', 'environment']);
            $table->index(['project_id', 'enabled']);
        });

        $this->enablePostgresRls('project_environments');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_environments');
    }

    private function enablePostgresRls(string $table): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("alter table {$table} enable row level security");
        }
    }
};
