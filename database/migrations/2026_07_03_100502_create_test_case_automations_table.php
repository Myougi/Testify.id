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
        Schema::create('test_case_automations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_case_id')->unique()->constrained('test_cases')->cascadeOnDelete();
            $table->string('driver', 40)->default('playwright_dsl');
            $table->unsignedSmallInteger('schema_version')->default(1);
            $table->string('status', 20)->default('Draft');
            $table->json('definition')->nullable();
            $table->char('source_hash', 64)->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->string('last_validated_environment', 32)->nullable();
            $table->timestamp('last_validated_at')->nullable();
            $table->text('validation_summary')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'updated_at']);
        });

        $this->enablePostgresRls('test_case_automations');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('test_case_automations');
    }

    private function enablePostgresRls(string $table): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("alter table {$table} enable row level security");
        }
    }
};
