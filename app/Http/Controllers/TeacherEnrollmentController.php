<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Handles the teacher-side of the admin "assign student to teacher" flow.
 *
 * An admin can set a student's teacher_id from the web dashboard
 * (AdminWebController::reassignTeacher / bulkCreateStudents), which puts
 * that student in enrollment_status = 'pending'. The student does NOT
 * show up in the teacher's class until the teacher explicitly enrolls
 * them here — this keeps the teacher in control of their own roster
 * instead of the admin silently populating it.
 */
class TeacherEnrollmentController extends Controller
{
    /**
     * GET /api/teachers/{teacherId}/pending-students
     *
     * Students an admin has assigned to this teacher but who have not
     * yet been enrolled into the teacher's class.
     */
    public function pendingStudents($teacherId)
    {
        $teacher = User::where('id', $teacherId)->where('role', 'teacher')->firstOrFail();

        $pending = User::where('role', 'student')
            ->where('teacher_id', $teacher->id)
            ->where('enrollment_status', 'pending')
            ->orderBy('grade_level')
            ->orderBy('section')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'name', 'lrn', 'grade_level', 'section']);

        return response()->json(['data' => $pending]);
    }

    /**
     * POST /api/teachers/{teacherId}/students/{studentId}/enroll
     *
     * The teacher accepts the assignment — the student is now officially
     * part of their class roster.
     */
    public function enroll(Request $request, $teacherId, $studentId)
    {
        $teacher = User::where('id', $teacherId)->where('role', 'teacher')->firstOrFail();
        $student = $this->assignedStudentOrFail($teacher->id, $studentId);

        if (!$student) {
            return response()->json([
                'message' => 'That student is not assigned to you, or no longer exists.',
            ], 404);
        }

        $student->enrollment_status = 'enrolled';
        $student->save();

        $studentRow = Student::where('user_id', $student->id)->first();
        if ($studentRow) {
            $studentRow->enrollment_status = 'enrolled';
            $studentRow->save();
        }

        return response()->json([
            'message' => "{$student->name} has been enrolled into your class.",
        ]);
    }

    /**
     * POST /api/teachers/{teacherId}/students/{studentId}/decline
     *
     * The teacher declines the assignment — the student goes back to
     * admin as unassigned rather than staying stuck "pending" under a
     * teacher who doesn't want them.
     */
    public function decline(Request $request, $teacherId, $studentId)
    {
        $teacher = User::where('id', $teacherId)->where('role', 'teacher')->firstOrFail();
        $student = $this->assignedStudentOrFail($teacher->id, $studentId);

        if (!$student) {
            return response()->json([
                'message' => 'That student is not assigned to you, or no longer exists.',
            ], 404);
        }

        $student->teacher_id = null;
        $student->enrollment_status = 'unassigned';
        $student->save();

        $studentRow = Student::where('user_id', $student->id)->first();
        if ($studentRow) {
            $studentRow->teacher_id = null;
            $studentRow->enrollment_status = 'unassigned';
            $studentRow->save();
        }

        return response()->json([
            'message' => "Declined. {$student->name} has been sent back to admin as unassigned.",
        ]);
    }

    private function assignedStudentOrFail($teacherId, $studentId): ?User
    {
        return User::where('id', $studentId)
            ->where('role', 'student')
            ->where('teacher_id', $teacherId)
            ->first();
    }
}