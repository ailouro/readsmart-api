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

        return view('admin.dashboard', compact(
            'tab', 'students', 'parents', 'childrenByParent', 'teachers'
        ));
    }

    // -----------------------------------------------------------------
    // BULK CREATE — STUDENTS
    // -----------------------------------------------------------------

    /**
     * Expects a textarea where each line is:
     *   first_name, last_name, lrn, grade_level, section
     */
    public function bulkCreateStudents(Request $request)
    {
        $request->validate([
            'roster' => 'required|string',
        ]);

        $rows    = $this->parseRoster($request->input('roster'), 5);
        $created = [];
        $errors  = [];

        foreach ($rows as $lineNo => $cols) {
            [$firstName, $lastName, $lrn, $gradeLevel, $section] = $cols;

            if (User::where('lrn', $lrn)->exists()) {
                $errors[] = "Line {$lineNo}: LRN {$lrn} is already registered — skipped.";
                continue;
            }

            $plainPassword = $this->generatePassword();

            try {
                $user = DB::transaction(function () use (
                    $firstName, $lastName, $lrn, $gradeLevel, $section, $plainPassword
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

                    Student::create([
                        'user_id'     => $user->id,
                        'first_name'  => $firstName,
                        'last_name'   => $lastName,
                        'name'        => trim("{$firstName} {$lastName}"),
                        'grade_level' => $gradeLevel,
                        'section'     => $section,
                    ]);

                    return $user;
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
                $errors[] = "Line {$lineNo}: failed to create {$firstName} {$lastName} — " . $e->getMessage();
            }
        }

        return redirect()
            ->route('admin.credentials')
            ->with('credentials', $created)
            ->with('credential_errors', $errors);
    }

    // -----------------------------------------------------------------
    // BULK CREATE — PARENTS
    // -----------------------------------------------------------------

    /**
     * Expects a textarea where each line is:
     *   first_name, last_name, email, child_lrn
     *
     * The child's LRN links this parent to an existing student account,
     * so the student must be created first.
     */
    public function bulkCreateParents(Request $request)
    {
        $request->validate([
            'roster' => 'required|string',
        ]);

        $rows    = $this->parseRoster($request->input('roster'), 4);
        $created = [];
        $errors  = [];

        foreach ($rows as $lineNo => $cols) {
            [$firstName, $lastName, $email, $childLrn] = $cols;

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Line {$lineNo}: '{$email}' is not a valid email — skipped.";
                continue;
            }

            if (User::where('email', $email)->exists()) {
                $errors[] = "Line {$lineNo}: {$email} is already registered — skipped.";
                continue;
            }

            $child = User::where('lrn', $childLrn)->where('role', 'student')->first();
            if (!$child) {
                $errors[] = "Line {$lineNo}: no student account found with LRN {$childLrn}. "
                          . "Create the student first, then re-add this parent — skipped.";
                continue;
            }

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

                    // Assigned directly rather than mass-assigned, in case
                    // parent_id is not in User::$fillable.
                    $child->parent_id = $parent->id;
                    $child->save();

                    // Keep the students table in sync too, where present.
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
                $errors[] = "Line {$lineNo}: failed to create {$email} — " . $e->getMessage();
            }
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
        // Passwords only exist in the flash payload from the bulk-create
        // redirect. Reloading this page after that flash is gone shows
        // nothing — by design, since plaintext is never persisted.
        $credentials = session('credentials', []);
        $errors      = session('credential_errors', []);

        return view('admin.credentials', compact('credentials', 'errors'));
    }

    // -----------------------------------------------------------------
    // HELPERS
    // -----------------------------------------------------------------

    /**
     * Split a pasted roster into trimmed columns, keyed by 1-based line
     * number so error messages can point at the offending row. Lines with
     * the wrong column count are skipped rather than silently mangled.
     */
    private function parseRoster(string $roster, int $expectedColumns): array
    {
        $out = [];

        foreach (preg_split('/\r\n|\r|\n/', $roster) as $i => $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $cols = array_map('trim', explode(',', $line));
            if (count($cols) !== $expectedColumns) {
                continue;
            }

            $out[$i + 1] = $cols;
        }

        return $out;
    }

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