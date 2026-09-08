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
        Schema::table('student_progress', function (Blueprint $table) {
            // Words Per Minute, computed client-side as
            // (total_words / time_on_task) * 60 and saved here so the
            // teacher dashboard doesn't need to recompute it every time.
            // Nullable: older rows, and interim progress rows saved before
            // the quiz is submitted, won't have a value.
            $table->decimal('wpm', 6, 2)->nullable()->after('time_on_task');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_progress', function (Blueprint $table) {
            $table->dropColumn('wpm');
        });
    }
};