<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SchoolClass;
use App\Models\User;

class ParentController extends Controller
{
    public function dashboard($parentId)
    {
        // Find the student linked to this parent
        $student = User::where('parent_id', $parentId)->where('role', 'student')->first();

        if (!$student) {
            // No linked child yet — return an empty-but-valid shape instead
            // of a 404, so the app doesn't need a special error branch just
            // to render its "not connected to a class yet" empty state.
            return response()->json([
                'student_id'   => null,
                'student_name' => null,
                'classes'      => [],
            ], 200);
        }

        // 🛠️ FIX: was ->classes()->first(), which silently dropped every
        // class after the first one a student was enrolled in. A student
        // can belong to more than one class (you have a class_student
        // pivot table for exactly this), so return all of them.
        $classes = $student->classes()->get()->map(function ($class) {
            return [
                'class_name'  => $class->name,
                'class_code'  => $class->class_code,
                'grade_level' => $class->grade_level,
            ];
        });

        return response()->json([
            'student_id'   => $student->id,
            'student_name' => $student->name,
            'classes'      => $classes,
        ], 200);
    }

    public function enroll(Request $request)
    {
        $request->validate([
            'parent_id'    => 'required|exists:users,id',
            'class_code'   => 'required|string',
            'student_name' => 'required|string|max:255',
        ]);

        $schoolClass = SchoolClass::where('class_code', $request->class_code)->first();

        if (!$schoolClass) {
            return response()->json([
                'message' => 'Invalid Class Code. Please check with the teacher.',
            ], 404);
        }

        // Find the existing student account by name
        $student = User::where('name', $request->student_name)
                       ->where('role', 'student')
                       ->first();

        if (!$student) {
            return response()->json([
                'message' => 'Child account not found. Ensure they have a student account and the name matches perfectly.',
            ], 404);
        }

        // Link the parent to the student
        $student->parent_id = $request->parent_id;
        $student->save();

        // Attach student to the class
        $schoolClass->students()->syncWithoutDetaching([$student->id]);

        // Return this student's full, up-to-date class list (not just the
        // one just joined) so a caller that doesn't immediately re-fetch
        // the dashboard still sees an accurate picture.
        $classes = $student->classes()->get()->map(function ($class) {
            return [
                'class_name'  => $class->name,
                'class_code'  => $class->class_code,
                'grade_level' => $class->grade_level,
            ];
        });

        return response()->json([
            'message'      => 'Student successfully enrolled in class!',
            'student_name' => $student->name,
            'student_id'   => $student->id,
            'classes'      => $classes,
        ], 200);
    }
}