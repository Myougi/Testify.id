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
        Schema::create('project_credential_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')
                ->constrained('projects', 'project_id')
                ->cascadeOnDelete();
            $table->string('environment', 32);
            $table->string('name');
            $table->string('key', 100);
            $table->json('fields');
            $table->timestamps();

            $table->unique(['project_id', 'environment', 'key'], 'project_credential_profiles_unique');
            $table->index(['project_id', 'environment']);
        });

        $this->enablePostgresRls('project_credential_profiles');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_credential_profiles');
    }

    private function enablePostgresRls(string $table): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("alter table {$table} enable row level security");
        }
    }
};
