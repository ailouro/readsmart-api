<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mispronunciations', function (Blueprint $table) {
            // 🛠️ FIX: This FK was pointing at the `students` table, but the
            // app's logged-in students (via JWT) live in `users`
            // (role='student'). StudentProgress.user_id already correctly
            // references `users` — mispronunciations.student_id must match
            // that same source, since Flutter sends the logged-in user's
            // `users.id`, not a `students.id`.
            $table->dropForeign('mispronunciations_student_id_foreign');
            $table->foreign('student_id')
                  ->references('id')->on('users')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('mispronunciations', function (Blueprint $table) {
            $table->dropForeign(['student_id']);
            $table->foreign('student_id')
                  ->references('id')->on('students')
                  ->onDelete('cascade');
        });
    }
};