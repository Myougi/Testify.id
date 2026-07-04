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
        Schema::create('automation_artifacts', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('automation_execution_id')->constrained('automation_executions')->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('disk', 40);
            $table->string('path', 2048);
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->char('sha256', 64);
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();

            $table->index(['automation_execution_id', 'type']);
        });

        $this->enablePostgresRls('automation_artifacts');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('automation_artifacts');
    }

    private function enablePostgresRls(string $table): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("alter table {$table} enable row level security");
        }
    }
};
