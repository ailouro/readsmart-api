<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ClassJoinRequest;
use App\Models\SchoolClass;
use App\Models\StudentRequest;
use App\Models\User;

class ParentController extends Controller
{
    public function dashboard($parentId)
    {
        $parent = User::find($parentId);
        $notifications = [];

        if ($parent) {
            // Approved student-account requests this parent hasn't seen a
            // notification for yet — surfaced regardless of whether a linked
            // child already resolves below, since approving a request is what
            // creates that linked child in the first place.
            // Prefixed "sr_" so its id can't collide with class-join-request
            // ids below when the app calls dismissNotification($id).
            $accountNotifications = StudentRequest::where('parent_email', $parent->email)
                ->where('status', 'approved')
                ->whereNull('notified_at')
                ->get()
                ->map(function ($req) {
                    return [
                        'id' => 'sr_' . $req->id,
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
                });

            // Class-join requests the teacher has acted on (approved or
            // declined) that this parent hasn't seen a notification for yet.
            // Prefixed "cjr_" — see note above.
            $classNotifications = ClassJoinRequest::with('schoolClass')
                ->where('parent_id', $parentId)
                ->whereIn('status', ['approved', 'declined'])
                ->whereNull('notified_at')
                ->get()
                ->map(function ($req) {
                    $className = $req->schoolClass->name ?? 'the class';
                    $approved = $req->status === 'approved';
                    return [
                        'id' => 'cjr_' . $req->id,
                        'message' => $approved
                            ? "🎉 {$req->student_name}'s request to join {$className} was approved by the teacher!"
                            : "{$req->student_name}'s request to join {$className} was declined by the teacher.",
                        'student_name' => $req->student_name,
                    ];
                });

            $notifications = $accountNotifications->concat($classNotifications)->values();
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
        // Only classes the student is actually attached to show up here —
        // a class_join_request stays "pending" until the teacher approves
        // it, so requested-but-not-yet-approved classes correctly don't
        // appear yet.
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
    // a notification dialog. Handles both notification kinds that feed
    // into the same list — "sr_{id}" for student-account-approval
    // notifications, "cjr_{id}" for class-join-request notifications —
    // dispatching to whichever table the prefix names.
    public function dismissNotification($id)
    {
        if (str_starts_with($id, 'cjr_')) {
            $req = ClassJoinRequest::find(substr($id, 4));
        } else {
            // Back-compat: older StudentRequest ids may still arrive
            // unprefixed from clients that haven't refreshed their app.
            $rawId = str_starts_with($id, 'sr_') ? substr($id, 3) : $id;
            $req = StudentRequest::find($rawId);
        }

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

        // Find the existing student account by name. We still check this
        // up front (instead of only at approval time) so the parent gets
        // an immediate, specific error if the name doesn't match, rather
        // than a request that silently sits pending forever.
        $student = User::where('name', $request->student_name)
                       ->where('role', 'student')
                       ->first();

        if (!$student) {
            return response()->json([
                'message' => 'Child account not found. Ensure they have a student account and the name matches perfectly.',
            ], 404);
        }

        // Already attached to this class? Nothing to request.
        $alreadyEnrolled = $schoolClass->students()
            ->where('users.id', $student->id)
            ->exists();

        if ($alreadyEnrolled) {
            return response()->json([
                'message' => "{$student->name} is already enrolled in this class.",
            ], 200);
        }

        // Already has a pending request for this exact class? Don't spam
        // the teacher with duplicates.
        $existingPending = ClassJoinRequest::where('school_class_id', $schoolClass->id)
            ->where('student_name', $student->name)
            ->where('status', 'pending')
            ->exists();

        if ($existingPending) {
            return response()->json([
                'message' => 'A request to join this class is already waiting for the teacher to review.',
            ], 200);
        }

        // 🛠️ Class joining now needs teacher approval: create a pending
        // request instead of immediately linking the parent and attaching
        // the student to the class. The teacher approves/declines it from
        // their dashboard notification bell (see TeacherClassRequestController).
        ClassJoinRequest::create([
            'parent_id'       => $request->parent_id,
            'student_name'    => $student->name,
            'school_class_id' => $schoolClass->id,
            'status'          => 'pending',
        ]);

        return response()->json([
            'message'      => 'Request sent! The teacher will need to approve it before your child is linked to the class.',
            'student_name' => $student->name,
        ], 201);
    }
}