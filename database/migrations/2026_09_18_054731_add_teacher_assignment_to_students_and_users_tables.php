<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds teacher assignment + enrollment tracking so an admin can assign a
 * student directly to a specific teacher, while the teacher still has to
 * explicitly enroll that student into their own class roster.
 *
 * enrollment_status values:
 *   'unassigned' — no teacher_id set (default; also the state after an
 *                  admin clears the assignment or a teacher declines).
 *   'pending'    — teacher_id is set, but the teacher has not yet
 *                  enrolled the student into their class.
 *   'enrolled'   — the teacher has accepted/enrolled the student.
 *
 * Mirrored on both `users` and `students` tables to match this app's
 * existing pattern of duplicating grade_level/section on both (see
 * AdminWebController::bulkCreateStudents / bulkCreateParents).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('teacher_id')->nullable()->after('parent_id')
                ->constrained('users')->nullOnDelete();
            $table->string('enrollment_status')->default('unassigned')->after('teacher_id');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->foreignId('teacher_id')->nullable()->after('parent_id')
                ->constrained('users')->nullOnDelete();
            $table->string('enrollment_status')->default('unassigned')->after('teacher_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('teacher_id');
            $table->dropColumn('enrollment_status');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->dropConstrainedForeignId('teacher_id');
            $table->dropColumn('enrollment_status');
        });
    }
};