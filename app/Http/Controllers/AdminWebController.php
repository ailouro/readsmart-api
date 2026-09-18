<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Admin web dashboard.
 *
 * Flow this implements:
 *   - Admin logs in with email + password (role must be 'admin').
 *   - Admin bulk-creates STUDENT accounts (login by LRN).
 *   - Admin bulk-creates PARENT accounts (login by email, linked to a
 *     child via that child's LRN).
 *   - Admin approves TEACHER accounts (teachers still self-register
 *     through the Flutter app's register screen).
 *   - After a bulk create, admin gets a printable credential sheet.
 *
 * Generated passwords are shown ONCE on the print page and never stored
 * in plaintext — only the bcrypt hash goes to the database. If the sheet
 * is lost, the account has to be given a new password.
 */
class AdminWebController extends Controller
{
    // Ambiguous characters (0/O, 1/l/I) are left out so kids and parents
    // can retype these from a printed slip without confusion.
    private const PASSWORD_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    // -----------------------------------------------------------------
    // AUTH
    // -----------------------------------------------------------------

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

    // -----------------------------------------------------------------
    // DASHBOARD (tabbed: students / parents / teachers)
    // -----------------------------------------------------------------

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

        // Only approved teachers should be selectable when assigning a
        // student — an unapproved teacher can't even log in yet, so a
        // student "assigned" to them would have nowhere to be enrolled.
        $approvedTeachers = $teachers->whereNotNull('email_verified_at')->values();

        return view('admin.dashboard', compact(
            'tab', 'students', 'parents', 'childrenByParent', 'teachers', 'approvedTeachers'
        ));
    }

    // -----------------------------------------------------------------
    // BULK CREATE — STUDENTS
    // -----------------------------------------------------------------

    /**
     * Expects 'rows' as an array of associative arrays:
     *   rows[i][first_name], rows[i][last_name], rows[i][lrn],
     *   rows[i][grade_level], rows[i][section]
     *
     * This replaces the old comma-separated-line parser, which silently
     * skipped any line whose comma count didn't match expectations (e.g.
     * a last name containing a comma, or a stray trailing comma) — no
     * error was ever shown, so the admin just saw "no accounts created"
     * with no explanation. Structured fields make that class of bug
     * impossible, and every skipped row now gets a specific reason.
     */
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

    // -----------------------------------------------------------------
    // STUDENTS — assign/reassign teacher
    // -----------------------------------------------------------------

    /**
     * Sets (or clears, if teacher_id is blank) which teacher a student is
     * assigned to. This does NOT enroll the student into the teacher's
     * class — it only puts them in enrollment_status = 'pending' so the
     * teacher sees them and can choose to enroll or decline on their end.
     * Reassigning a student who was already enrolled resets them back to
     * 'pending' under the new teacher, since the new teacher hasn't
     * agreed to take them on yet.
     */
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
    // ACCOUNTS — reset password (student, parent, or teacher)
    // -----------------------------------------------------------------

    /**
     * Generates a brand-new password for any account and reuses the same
     * "shown once, never stored in plaintext" credential-sheet flow as
     * bulk create. The old password stops working immediately.
     */
    public function resetPassword($id)
    {
        $user = User::findOrFail($id);
        $plainPassword = $this->generatePassword();

        $user->password = Hash::make($plainPassword);
        $user->save();

        $isStudent = $user->role === 'student';

        $credential = [
            'type'        => $user->role,
            'name'        => $user->name,
            'login'       => $isStudent ? $user->lrn : $user->email,
            'login_label' => $isStudent ? 'LRN' : 'Email',
            'password'    => $plainPassword,
            'grade_level' => $user->grade_level,
            'section'     => $user->section,
        ];

        return redirect()
            ->route('admin.credentials')
            ->with('credentials', [$credential])
            ->with('credential_errors', []);
    }

    // -----------------------------------------------------------------
    // BULK CREATE — PARENTS
    // -----------------------------------------------------------------

    /**
     * Expects 'rows' as an array of associative arrays:
     *   rows[i][first_name], rows[i][last_name], rows[i][email],
     *   rows[i][child_lrn]
     *
     * The child's LRN links this parent to an existing student account,
     * so the student must already exist.
     */
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

    public function approveTeacher($id)
    {
        $teacher = User::where('role', 'teacher')->findOrFail($id);
        $teacher->email_verified_at = now();
        $teacher->save();

        return back()->with('success', "{$teacher->name} can now log in.");
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