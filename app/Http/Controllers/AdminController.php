<?php

namespace App\Http\Controllers;

use App\Models\Student;
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
}