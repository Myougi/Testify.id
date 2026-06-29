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
        Schema::table('issues', function (Blueprint $table) {
            $table->text('expected_result')->nullable()->after('description');
            $table->text('actual_result')->nullable()->after('expected_result');
            $table->string('status')->default('Open')->change();
        });

        DB::table('issues')
            ->where('status', 'New')
            ->update(['status' => 'Open']);

        DB::table('issues')
            ->whereIn('status', ['Fixed', 'Closed'])
            ->update(['status' => 'Solved']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('issues')
            ->where('status', 'Solved')
            ->update(['status' => 'Closed']);

        DB::table('issues')
            ->where('status', 'In Investigation')
            ->update(['status' => 'Open']);

        DB::table('issues')
            ->where('status', 'In Testing')
            ->update(['status' => 'Open']);

        DB::table('issues')
            ->where('status', 'Deferred')
            ->update(['status' => 'Open']);

        Schema::table('issues', function (Blueprint $table) {
            $table->dropColumn(['expected_result', 'actual_result']);
            $table->enum('status', ['New', 'Open', 'Fixed', 'Closed'])->default('New')->change();
        });
    }
};
