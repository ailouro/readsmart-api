<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_progress', function (Blueprint $table) {
            $table->enum('test_type', ['pre_test', 'post_test'])->default('post_test')->after('story_id');
            $table->enum('reading_level', ['frustration', 'instructional', 'independent'])->nullable()->after('oral_fluency_accuracy');
            $table->enum('status', ['in_progress', 'completed'])->default('completed')->after('time_on_task');
        });
    }

    public function down(): void
    {
        Schema::table('student_progress', function (Blueprint $table) {
            $table->dropColumn(['test_type', 'reading_level', 'status']);
        });
    }
};