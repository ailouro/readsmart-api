<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\StudentRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    public function createStudent(Request $request)
    {
        $request->validate([
            'first_name' => 'required',
            'last_name' => 'required',
            'lrn' => 'required|unique:users',
            'grade_level' => 'required',
            'section' => 'required'
        ]);

        $result = DB::transaction(function () use ($request) {
            $user = User::create([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'lrn' => $request->lrn,
                'grade_level' => $request->grade_level,
                'section' => $request->section,
                'role' => 'student',
                'password' => Hash::make('readsmart123')
            ]);

            $student = Student::create([
                'user_id' => $user->id,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'name' => trim("{$request->first_name} {$request->last_name}"),
                'grade_level' => $request->grade_level,
                'section' => $request->section,
            ]);

            return [$user, $student];
        });

        [$user, $student] = $result;

        return response()->json([
            'message' => 'Student created successfully',
            'student' => $student,
            'user' => $user
        ]);
    }

    // ============================================================================
    // 🔐 ADMIN APPROVAL PAGE — replaces email verification for teacher/parent
    // accounts. Open in a browser: /admin/approvals?key=YOUR_SECRET_KEY
    //
    // The key must match ADMIN_PANEL_KEY in your .env / Railway variables.
    // This is a lightweight guard, not real auth — good enough for a
    // capstone, but don't share the URL publicly.
    // ============================================================================
    public function pendingApprovals(Request $request)
    {
        if ($request->query('key') !== env('ADMIN_PANEL_KEY')) {
            return response('<h2>403 — Invalid or missing key.</h2>', 403)
                ->header('Content-Type', 'text/html');
        }

        $pending = User::whereIn('role', ['teacher', 'parent'])
            ->whereNull('email_verified_at')
            ->orderBy('created_at', 'desc')
            ->get();

        $rows = '';
        foreach ($pending as $user) {
            $rows .= '
                <tr>
                    <td>' . e($user->name) . '</td>
                    <td>' . e($user->email) . '</td>
                    <td>' . e(ucfirst($user->role)) . '</td>
                    <td>' . e($user->created_at->format('Y-m-d H:i')) . '</td>
                    <td>
                        <form method="POST" action="/api/admin/approvals/' . $user->id . '/approve?key=' . urlencode($request->query('key')) . '" style="margin:0;">
                            <button type="submit" style="background:#16a34a;color:white;border:none;padding:6px 14px;border-radius:6px;cursor:pointer;">
                                Approve
                            </button>
                        </form>
                    </td>
                </tr>';
        }

        if ($pending->isEmpty()) {
            $rows = '<tr><td colspan="5" style="text-align:center;color:#888;padding:20px;">No pending accounts 🎉</td></tr>';
        }

        return response('
            <div style="font-family: Arial, sans-serif; max-width: 900px; margin: 40px auto; padding: 0 20px;">
                <h1>Pending Teacher / Parent Approvals</h1>
                <table style="width:100%; border-collapse: collapse;" border="1" cellpadding="10">
                    <thead style="background:#f3f4f6;">
                        <tr>
                            <th>Name</th><th>Email</th><th>Role</th><th>Registered</th><th>Action</th>
                        </tr>
                    </thead>
                    <tbody>' . $rows . '</tbody>
                </table>
            </div>
        ', 200)->header('Content-Type', 'text/html');
    }

    public function approveAccount(Request $request, $id)
    {
        if ($request->query('key') !== env('ADMIN_PANEL_KEY')) {
            return response('<h2>403 — Invalid or missing key.</h2>', 403)
                ->header('Content-Type', 'text/html');
        }

        $user = User::find($id);
        if ($user) {
            $user->email_verified_at = now();
            $user->save();
        }

        return redirect('/api/admin/approvals?key=' . urlencode($request->query('key')));
    }

    // ============================================================================
    // 👨‍👩‍👧 STUDENT ACCOUNT REQUESTS — parents (or students, citing a parent's
    // email) request a student account via requestStudentAccount(). This page
    // lets an admin review each request and either approve it (creates the
    // actual User + Student, links it to the parent) or reject it.
    // Open in a browser: /admin/student-requests?key=YOUR_SECRET_KEY
    // ============================================================================
    public function pendingStudentRequests(Request $request)
    {
        if ($request->query('key') !== env('ADMIN_PANEL_KEY')) {
            return response('<h2>403 — Invalid or missing key.</h2>', 403)
                ->header('Content-Type', 'text/html');
        }

        $pending = StudentRequest::where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get();

        $rows = '';
        foreach ($pending as $req) {
            $key = urlencode($request->query('key'));
            $rows .= '
                <tr>
                    <td>' . e($req->student_name) . '</td>
                    <td>' . e($req->lrn) . '</td>
                    <td>' . e($req->grade_level) . '</td>
                    <td>' . e($req->section) . '</td>
                    <td>' . e($req->parent_email) . '</td>
                    <td>
                        <form method="POST" action="/api/admin/student-requests/' . $req->id . '/approve?key=' . $key . '" style="display:inline; margin:0;">
                            <button type="submit" style="background:#16a34a;color:white;border:none;padding:6px 14px;border-radius:6px;cursor:pointer;">Approve</button>
                        </form>
                        <form method="POST" action="/api/admin/student-requests/' . $req->id . '/reject?key=' . $key . '" style="display:inline; margin:0 0 0 6px;">
                            <button type="submit" style="background:#dc2626;color:white;border:none;padding:6px 14px;border-radius:6px;cursor:pointer;">Reject</button>
                        </form>
                    </td>
                </tr>';
        }

        if ($pending->isEmpty()) {
            $rows = '<tr><td colspan="6" style="text-align:center;color:#888;padding:20px;">No pending student requests 🎉</td></tr>';
        }

        return response('
            <div style="font-family: Arial, sans-serif; max-width: 1000px; margin: 40px auto; padding: 0 20px;">
                <h1>Pending Student Account Requests</h1>
                <table style="width:100%; border-collapse: collapse;" border="1" cellpadding="10">
                    <thead style="background:#f3f4f6;">
                        <tr>
                            <th>Student Name</th><th>LRN</th><th>Grade</th><th>Section</th><th>Parent Email</th><th>Action</th>
                        </tr>
                    </thead>
                    <tbody>' . $rows . '</tbody>
                </table>
            </div>
        ', 200)->header('Content-Type', 'text/html');
    }

    public function approveStudentRequest(Request $request, $id)
    {
        if ($request->query('key') !== env('ADMIN_PANEL_KEY')) {
            return response('<h2>403 — Invalid or missing key.</h2>', 403)
                ->header('Content-Type', 'text/html');
        }

        $req = StudentRequest::find($id);
        $backUrl = '/api/admin/student-requests?key=' . urlencode($request->query('key'));

        if (!$req || $req->status !== 'pending') {
            return redirect($backUrl);
        }

        // The parent must already have a registered account (this was
        // checked when the request was first submitted, but re-check here
        // in case anything changed since).
        $parent = User::where('email', $req->parent_email)->where('role', 'parent')->first();
        if (!$parent) {
            return response(
                '<h2>Cannot approve — no parent account found for ' . e($req->parent_email) . '.</h2>
                 <a href="' . $backUrl . '">Back</a>',
                422
            )->header('Content-Type', 'text/html');
        }

        // LRN could theoretically have been taken by something else since
        // the request was submitted — re-check before creating.
        if (User::where('lrn', $req->lrn)->exists()) {
            return response(
                '<h2>Cannot approve — LRN ' . e($req->lrn) . ' is already in use.</h2>
                 <a href="' . $backUrl . '">Back</a>',
                422
            )->header('Content-Type', 'text/html');
        }

        // student_requests only stores one "student_name" field, not
        // separate first/last names like users/students need. Splitting on
        // the first space is a rough approximation — multi-part first names
        // (e.g. "Maria Clara Santos") will split incorrectly. Worth fixing
        // at the source (asking for first/last name separately on the
        // request form) if this matters for your records.
        $parts = explode(' ', trim($req->student_name), 2);
        $firstName = $parts[0];
        $lastName = $parts[1] ?? '';

        DB::transaction(function () use ($req, $parent, $firstName, $lastName) {
            $user = User::create([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'name' => trim("{$firstName} {$lastName}"),
                'lrn' => $req->lrn,
                'grade_level' => $req->grade_level,
                'section' => $req->section,
                'role' => 'student',
                'password' => Hash::make('readsmart123'),
            ]);

            // Set parent_id via direct assignment rather than passing it to
            // create() — ParentController::enroll() does it this way too,
            // and it works regardless of whether parent_id is in User's
            // $fillable array (mass-assignment would silently fail if not).
            $user->parent_id = $parent->id;
            $user->save();

            Student::create([
                'user_id' => $user->id,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'name' => trim("{$firstName} {$lastName}"),
                'grade_level' => $req->grade_level,
                'section' => $req->section,
            ]);

            $req->status = 'approved';
            $req->save();
        });

        return redirect($backUrl);
    }

    public function rejectStudentRequest(Request $request, $id)
    {
        if ($request->query('key') !== env('ADMIN_PANEL_KEY')) {
            return response('<h2>403 — Invalid or missing key.</h2>', 403)
                ->header('Content-Type', 'text/html');
        }

        $req = StudentRequest::find($id);
        if ($req && $req->status === 'pending') {
            $req->status = 'rejected';
            $req->save();
        }

        return redirect('/api/admin/student-requests?key=' . urlencode($request->query('key')));
    }
}