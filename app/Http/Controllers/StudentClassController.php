<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SchoolClass;
use App\Models\User;

class StudentClassController extends Controller
{
    // 1. Join class by code (Student side)
    public function joinClass(Request $request)
    {
        $request->validate([
            'class_code' => 'required|string',
            'student_id' => 'required',
        ]);

        $user = User::find($request->student_id);

        if (!$user) {
            return response()->json(['message' => 'Student account not found.'], 404);
        }

        $class = SchoolClass::where('class_code', $request->class_code)->first();

        if (!$class) {
            return response()->json(['message' => 'Class code not found.'], 404);
        }

        // Attach student to class in pivot table
        $user->classes()->syncWithoutDetaching([$class->id]);

        return response()->json([
            'message' => 'Successfully joined ' . $class->name,
            'class'   => $class,
        ], 200);
    }

    // 2. Get joined classes for student (Student side)
    public function myClasses($student_id)
    {
        $user = User::find($student_id);

        if (!$user) {
            return response()->json(['classes' => []], 200);
        }

        return response()->json([
            'classes' => $user->classes()->get()
        ], 200);
    }

    // 3. Get list of students enrolled in a class (Teacher side)
    public function getClassStudents($class_id)
    {
        $class = SchoolClass::with('students')->find($class_id);

        if (!$class) {
            return response()->json(['message' => 'Class not found.'], 404);
        }

        return response()->json([
            'students' => $class->students
        ], 200);
    }
}