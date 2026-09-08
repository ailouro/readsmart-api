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
            $table->integer('current_slide')->default(0);
            $table->boolean('is_reading_completed')->default(false);
            $table->integer('quiz_score')->nullable()->change();
            $table->integer('total_questions')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_progress', function (Blueprint $table) {
            $table->dropColumn('current_slide');
            $table->dropColumn('is_reading_completed');
            $table->integer('quiz_score')->nullable(false)->change();
            $table->integer('total_questions')->nullable(false)->change();
        });
    }
};
