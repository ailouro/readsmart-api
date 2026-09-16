<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClassJoinRequest;
use App\Models\SchoolClass;
use App\Models\User;

class TeacherClassRequestController extends Controller
{
    // Pending "parent wants to join this class" requests for every class
    // this teacher owns. Powers the notification bell + badge count on
    // the teacher dashboard.
    public function index($teacherId)
    {
        $requests = ClassJoinRequest::with(['parent', 'schoolClass'])
            ->whereHas('schoolClass', function ($query) use ($teacherId) {
                $query->where('teacher_id', $teacherId);
            })
            ->where('status', 'pending')
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($req) {
                return [
                    'id'           => $req->id,
                    'student_name' => $req->student_name,
                    'class_name'   => $req->schoolClass->name ?? 'Class',
                    'parent_name'  => $req->parent->name ?? 'A parent',
                    'created_at'   => $req->created_at,
                ];
            });

        return response()->json(['data' => $requests], 200);
    }

    // Teacher taps the ✅ check icon: link the parent to the student and
    // attach the student to the class — this is exactly what
    // ParentController::enroll() used to do immediately, now gated behind
    // this approval. notified_at stays null so the parent's dashboard
    // bell picks up the "approved" notification on its next fetch.
    public function approve($id)
    {
        $req = ClassJoinRequest::with('schoolClass')->find($id);

        if (!$req || $req->status !== 'pending') {
            return response()->json(['message' => 'Request not found or already handled.'], 404);
        }

        $student = User::where('name', $req->student_name)
            ->where('role', 'student')
            ->first();

        if (!$student) {
            return response()->json([
                'message' => 'Could not find a student account matching this name anymore.',
            ], 404);
        }

        $student->parent_id = $req->parent_id;
        $student->save();

        $req->schoolClass->students()->syncWithoutDetaching([$student->id]);

        $req->status = 'approved';
        $req->save();

        return response()->json(['success' => true, 'message' => 'Request approved.'], 200);
    }

    // Teacher taps the ❌ icon: no linking happens, the parent just gets
    // notified that this particular request was declined.
    public function decline($id)
    {
        $req = ClassJoinRequest::find($id);

        if (!$req || $req->status !== 'pending') {
            return response()->json(['message' => 'Request not found or already handled.'], 404);
        }

        $req->status = 'declined';
        $req->save();

        return response()->json(['success' => true, 'message' => 'Request declined.'], 200);
    }
}