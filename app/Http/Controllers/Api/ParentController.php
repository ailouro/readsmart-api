<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SchoolClass;
use App\Models\StudentRequest;
use App\Models\User;

class ParentController extends Controller
{
    public function dashboard($parentId)
    {
        // Approved student-account requests this parent hasn't seen a
        // notification for yet — surfaced regardless of whether a linked
        // child already resolves below, since approving a request is what
        // creates that linked child in the first place.
        $parent = User::find($parentId);
        $notifications = [];
        if ($parent) {
            $notifications = StudentRequest::where('parent_email', $parent->email)
                ->where('status', 'approved')
                ->whereNull('notified_at')
                ->get()
                ->map(function ($req) {
                    return [
                        'id' => $req->id,
                        'message' => "Good news! {$req->student_name}'s account has been approved.",
                        'student_name' => $req->student_name,
                        'lrn' => $req->lrn,
                        // Matches the hardcoded default set in
                        // AdminController::approveStudentRequest and
                        // ::createStudent. If that default ever changes,
                        // update it here too so parents see the right
                        // password.
                        'password' => 'readsmart123',
                    ];
                })
                ->values();
        }

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
                'notifications' => $notifications,
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
            'notifications' => $notifications,
        ], 200);
    }

    // Marks a notification as seen so it doesn't keep showing on every
    // dashboard load. Called by the app right after the parent dismisses
    // the "your child's account was approved" dialog.
    public function dismissNotification($id)
    {
        $req = StudentRequest::find($id);
        if ($req) {
            $req->notified_at = now();
            $req->save();
        }
        return response()->json(['success' => true]);
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