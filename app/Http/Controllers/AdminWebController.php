<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;


class AdminWebController extends Controller
{

    private const PASSWORD_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

 
    public function showLogin()
    {
        if (Auth::guard('web')->check() && Auth::guard('web')->user()->role === 'admin') {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if (!Auth::guard('web')->attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Those credentials do not match our records.']);
        }

        if (Auth::guard('web')->user()->role !== 'admin') {
            Auth::guard('web')->logout();
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'That account is not an administrator.']);
        }

        $request->session()->regenerate();

        return redirect()->route('admin.dashboard');
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

    public function index(Request $request)
    {
        $tab = $request->query('tab', 'students');
        if (!in_array($tab, ['students', 'parents', 'teachers'], true)) {
            $tab = 'students';
        }

        $students = User::where('role', 'student')
            ->orderBy('grade_level')
            ->orderBy('section')
            ->orderBy('last_name')
            ->get();

        $parents = User::where('role', 'parent')
            ->orderBy('last_name')
            ->orderBy('name')
            ->get();

        // Map each parent to the children linked via users.parent_id, so
        // the Parents tab can show who each account belongs to.
        $childrenByParent = User::where('role', 'student')
            ->whereNotNull('parent_id')
            ->get()
            ->groupBy('parent_id');

        $teachers = User::where('role', 'teacher')
            ->orderBy('created_at', 'desc')
            ->get();


        $approvedTeachers = $teachers->whereNotNull('email_verified_at')->values();

        return view('admin.dashboard', compact(
            'tab', 'students', 'parents', 'childrenByParent', 'teachers', 'approvedTeachers'
        ));
    }

    public function bulkCreateStudents(Request $request)
    {
        $request->validate([
            'rows'   => 'required|array|min:1',
            'rows.*' => 'array',
        ]);

        $created = [];
        $errors  = [];
        $seenLrns = [];

        foreach ($request->input('rows') as $i => $row) {
            $rowNum = $i + 1;

            $firstName  = trim((string) ($row['first_name']  ?? ''));
            $lastName   = trim((string) ($row['last_name']   ?? ''));
            $lrn        = trim((string) ($row['lrn']         ?? ''));
            $gradeLevel = trim((string) ($row['grade_level'] ?? ''));
            $section    = trim((string) ($row['section']     ?? ''));
            $teacherIdRaw = trim((string) ($row['teacher_id'] ?? ''));

            // Skip fully blank rows without complaint — those are just
            // unused rows left over in the grid, not a mistake.
            if ($firstName === '' && $lastName === '' && $lrn === '' && $gradeLevel === '' && $section === '') {
                continue;
            }

            $missing = [];
            if ($firstName === '')  $missing[] = 'First Name';
            if ($lastName === '')   $missing[] = 'Last Name';
            if ($lrn === '')        $missing[] = 'LRN';
            if ($gradeLevel === '') $missing[] = 'Grade Level';
            if ($section === '')    $missing[] = 'Section';

            if (!empty($missing)) {
                $errors[] = "Row {$rowNum}: missing " . implode(', ', $missing) . '.';
                continue;
            }

            if (isset($seenLrns[$lrn])) {
                $errors[] = "Row {$rowNum}: LRN {$lrn} is duplicated within this batch (also on row {$seenLrns[$lrn]}) — skipped.";
                continue;
            }

            if (User::where('lrn', $lrn)->exists()) {
                $errors[] = "Row {$rowNum}: LRN {$lrn} is already registered — skipped.";
                continue;
            }

            // Teacher assignment is optional. A bad/unapproved id doesn't
            // fail the whole row — the student is still created, just
            // left unassigned, with a warning so the admin can fix it
            // from the "Existing students" table afterward.
            $teacherId = null;
            if ($teacherIdRaw !== '') {
                $validTeacher = User::where('id', $teacherIdRaw)
                    ->where('role', 'teacher')
                    ->whereNotNull('email_verified_at')
                    ->exists();

                if ($validTeacher) {
                    $teacherId = (int) $teacherIdRaw;
                } else {
                    $errors[] = "Row {$rowNum}: the selected teacher is not valid or not yet approved — {$firstName} {$lastName} was created unassigned.";
                }
            }

            $seenLrns[$lrn] = $rowNum;
            $plainPassword  = $this->generatePassword();

            try {
                DB::transaction(function () use (
                    $firstName, $lastName, $lrn, $gradeLevel, $section, $plainPassword, $teacherId
                ) {
                    $user = User::create([
                        'first_name'  => $firstName,
                        'last_name'   => $lastName,
                        'name'        => trim("{$firstName} {$lastName}"),
                        'lrn'         => $lrn,
                        'grade_level' => $gradeLevel,
                        'section'     => $section,
                        'role'        => 'student',
                        'password'    => Hash::make($plainPassword),
                    ]);

                    // Direct assignment (not mass-assignment) so this works
                    // regardless of whether teacher_id/enrollment_status are
                    // in User's $fillable — same approach used elsewhere in
                    // this app for parent_id.
                    $user->teacher_id = $teacherId;
                    $user->enrollment_status = $teacherId ? 'pending' : 'unassigned';
                    $user->save();

                    $student = Student::create([
                        'user_id'     => $user->id,
                        'first_name'  => $firstName,
                        'last_name'   => $lastName,
                        'name'        => trim("{$firstName} {$lastName}"),
                        'grade_level' => $gradeLevel,
                        'section'     => $section,
                    ]);

                    $student->teacher_id = $teacherId;
                    $student->enrollment_status = $teacherId ? 'pending' : 'unassigned';
                    $student->save();
                });

                $created[] = [
                    'type'        => 'student',
                    'name'        => trim("{$firstName} {$lastName}"),
                    'login'       => $lrn,
                    'login_label' => 'LRN',
                    'password'    => $plainPassword,
                    'grade_level' => $gradeLevel,
                    'section'     => $section,
                ];
            } catch (\Exception $e) {
                $errors[] = "Row {$rowNum}: failed to create {$firstName} {$lastName} — " . $e->getMessage();
            }
        }

        if (empty($created) && empty($errors)) {
            $errors[] = 'No rows had any data — nothing was submitted.';
        }

        return redirect()
            ->route('admin.credentials')
            ->with('credentials', $created)
            ->with('credential_errors', $errors);
    }

    public function reassignTeacher(Request $request, $id)
    {
        $request->validate([
            'teacher_id' => 'nullable',
        ]);

        $student = User::where('role', 'student')->findOrFail($id);

        $teacherIdRaw = trim((string) $request->input('teacher_id', ''));
        $teacherId = null;

        if ($teacherIdRaw !== '') {
            $teacher = User::where('id', $teacherIdRaw)
                ->where('role', 'teacher')
                ->whereNotNull('email_verified_at')
                ->first();

            if (!$teacher) {
                return back()->withErrors([
                    'teacher_id' => 'That teacher account is not approved or does not exist.',
                ]);
            }

            $teacherId = $teacher->id;
        }

        DB::transaction(function () use ($student, $teacherId) {
            $student->teacher_id = $teacherId;
            $student->enrollment_status = $teacherId ? 'pending' : 'unassigned';
            $student->save();

            $studentRow = Student::where('user_id', $student->id)->first();
            if ($studentRow) {
                $studentRow->teacher_id = $teacherId;
                $studentRow->enrollment_status = $teacherId ? 'pending' : 'unassigned';
                $studentRow->save();
            }
        });

        return back()->with('success', $teacherId
            ? "{$student->name} has been assigned to a teacher and is now pending their enrollment."
            : "{$student->name} is now unassigned from any teacher.");
    }

    // -----------------------------------------------------------------
    // ACCOUNTS — admin changes their own password
    // -----------------------------------------------------------------

    /**
     * Unlike resetPassword() (which resets someone else's account and
     * needs no confirmation), this changes the currently logged-in
     * admin's own password, so it requires their current password first.
     */
    public function changeOwnPassword(Request $request)
    {
        $admin = Auth::guard('web')->user();
        if (!$admin) {
            return redirect()->route('admin.login');
        }

        // Basic rule para sa pangalan
        $rules = [
            'name' => 'required|string|max:255',
        ];

        // I-validate lamang ang password kung may ini-type sa mga password fields
        if ($request->filled('current_password') || $request->filled('new_password')) {
            $rules['current_password'] = 'required|string';
            $rules['new_password']     = 'required|string|min:8|confirmed';
        }

        $request->validate($rules, [
            'name.required'             => 'Please enter your name.',
            'current_password.required' => 'Please enter your current password to change it.',
            'new_password.required'     => 'Please enter a new password.',
            'new_password.min'          => 'New password must be at least 8 characters.',
            'new_password.confirmed'    => 'New password and confirmation do not match.',
        ]);

        // I-update ang pangalan
        $admin->name = $request->input('name');

        // I-update ang password kung may nilagay na inputs
        if ($request->filled('current_password')) {
            if (!Hash::check($request->input('current_password'), $admin->password)) {
                return back()->withErrors(['current_password' => 'Your current password is incorrect.']);
            }
            $admin->password = Hash::make($request->input('new_password'));
        }

        $admin->save();

        return back()->with('success', 'Account settings updated successfully.');
    }
   
    public function resetPassword($id)
{
    $user = User::findOrFail($id);

    // Gawing fixed na 'readsmart123' sa halip na $this->generatePassword()
    $newPassword = 'readsmart123';

    $user->password = Hash::make($newPassword);
    $user->save();

    return back()->with('success', "Password for {$user->name} has been reset to '{$newPassword}'.");
}

   
    public function bulkCreateParents(Request $request)
    {
        $request->validate([
            'rows'   => 'required|array|min:1',
            'rows.*' => 'array',
        ]);

        $created = [];
        $errors  = [];
        $seenEmails = [];

        foreach ($request->input('rows') as $i => $row) {
            $rowNum = $i + 1;

            $firstName = trim((string) ($row['first_name'] ?? ''));
            $lastName  = trim((string) ($row['last_name']  ?? ''));
            $email     = trim((string) ($row['email']      ?? ''));
            $childLrn  = trim((string) ($row['child_lrn']  ?? ''));

            if ($firstName === '' && $lastName === '' && $email === '' && $childLrn === '') {
                continue;
            }

            $missing = [];
            if ($firstName === '') $missing[] = 'First Name';
            if ($lastName === '')  $missing[] = 'Last Name';
            if ($email === '')     $missing[] = 'Email';
            if ($childLrn === '')  $missing[] = "Child's LRN";

            if (!empty($missing)) {
                $errors[] = "Row {$rowNum}: missing " . implode(', ', $missing) . '.';
                continue;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Row {$rowNum}: '{$email}' is not a valid email — skipped.";
                continue;
            }

            if (isset($seenEmails[$email])) {
                $errors[] = "Row {$rowNum}: {$email} is duplicated within this batch (also on row {$seenEmails[$email]}) — skipped.";
                continue;
            }

            if (User::where('email', $email)->exists()) {
                $errors[] = "Row {$rowNum}: {$email} is already registered — skipped.";
                continue;
            }

            $child = User::where('lrn', $childLrn)->where('role', 'student')->first();
            if (!$child) {
                $errors[] = "Row {$rowNum}: no student account found with LRN {$childLrn}. "
                          . "Create the student first, then re-add this parent — skipped.";
                continue;
            }

            $seenEmails[$email] = $rowNum;
            $plainPassword = $this->generatePassword();

            try {
                DB::transaction(function () use (
                    $firstName, $lastName, $email, $plainPassword, $child
                ) {
                    $parent = User::create([
                        'first_name' => $firstName,
                        'last_name'  => $lastName,
                        'name'       => trim("{$firstName} {$lastName}"),
                        'email'      => $email,
                        'role'       => 'parent',
                        'password'   => Hash::make($plainPassword),
                    ]);

                    // Admin-created accounts are pre-approved. AuthController
                    // blocks parent/teacher logins without email_verified_at,
                    // so this must be set or the parent cannot sign in.
                    $parent->email_verified_at = now();
                    $parent->save();

                    $child->parent_id = $parent->id;
                    $child->save();

                    $studentRow = Student::where('user_id', $child->id)->first();
                    if ($studentRow) {
                        $studentRow->parent_id = $parent->id;
                        $studentRow->save();
                    }
                });

                $created[] = [
                    'type'        => 'parent',
                    'name'        => trim("{$firstName} {$lastName}"),
                    'login'       => $email,
                    'login_label' => 'Email',
                    'password'    => $plainPassword,
                    'child_name'  => $child->name,
                    'child_lrn'   => $childLrn,
                ];
            } catch (\Exception $e) {
                $errors[] = "Row {$rowNum}: failed to create {$email} — " . $e->getMessage();
            }
        }

        if (empty($created) && empty($errors)) {
            $errors[] = 'No rows had any data — nothing was submitted.';
        }

        return redirect()
            ->route('admin.credentials')
            ->with('credentials', $created)
            ->with('credential_errors', $errors);
    }

    // -----------------------------------------------------------------
    // TEACHERS — approve / revoke (teachers still self-register)
    // -----------------------------------------------------------------

    // Approves a teacher AND assigns the grade level they will handle.
    // Also used on already-approved teachers to change their grade level
    // (their original approval date is kept).
    public function approveTeacher(Request $request, $id)
    {
        $data = $request->validate([
            'grade_level' => 'required|in:Grade 5,Grade 6',
        ], [
            'grade_level.required' => 'Please choose a grade level before approving the teacher.',
            'grade_level.in'       => 'Grade level must be Grade 5 or Grade 6.',
        ]);

        $teacher = User::where('role', 'teacher')->findOrFail($id);
        $wasApproved = $teacher->email_verified_at !== null;

        if (!$wasApproved) {
            $teacher->email_verified_at = now();
        }
        // Direct assignment (not mass-assignment) so this works even if
        // grade_level is not in User's $fillable array.
        $teacher->grade_level = $data['grade_level'];
        $teacher->save();

        return back()->with('success', $wasApproved
            ? "{$teacher->name}'s grade level is now {$data['grade_level']}."
            : "{$teacher->name} can now log in and is assigned to {$data['grade_level']}.");
    }

    public function revokeTeacher($id)
    {
        $teacher = User::where('role', 'teacher')->findOrFail($id);
        $teacher->email_verified_at = null;
        $teacher->save();

        return back()->with('success', "{$teacher->name}'s access has been revoked.");
    }

    // -----------------------------------------------------------------
    // PRINTABLE CREDENTIAL SHEET
    // -----------------------------------------------------------------

    public function credentials(Request $request)
{
    $credentials  = session('credentials', []);
    $importErrors = session('credential_errors', []);

    return view('admin.credentials', compact('credentials', 'importErrors'));
}

    // -----------------------------------------------------------------
    // HELPERS
    // -----------------------------------------------------------------

    private function generatePassword(int $length = 7): string
    {
        $alphabet = self::PASSWORD_ALPHABET;
        $max      = strlen($alphabet) - 1;
        $password = '';

        for ($i = 0; $i < $length; $i++) {
            $password .= $alphabet[random_int(0, $max)];
        }

        return $password;
    }

    
}