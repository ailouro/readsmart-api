<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;


class AdminWebController extends Controller
{

    private const PASSWORD_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    // Grade levels teachers can be approved for (see approveTeacher) and
    // the section names offered in the "Create class" dropdown. Add more
    // section names here if a grade ever needs more than two.
    private const CLASS_GRADES   = ['Grade 5', 'Grade 6'];
    private const CLASS_SECTIONS = ['Section 1', 'Section 2'];

 
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

        $navCounts   = $this->navCounts();
        $classLabels = $this->classLabelsByStudent();

        // Students whose original password is still saved for reprinting
        // (user_id => true). Whether they have since changed it is checked
        // when the slips are actually printed — see reprintCredentials().
        $reprintable = Schema::hasTable('student_initial_passwords')
            ? DB::table('student_initial_passwords')->pluck('user_id')->flip()->map(fn () => true)->all()
            : [];

        return view('admin.dashboard', compact(
            'tab', 'students', 'parents', 'childrenByParent', 'teachers', 'approvedTeachers',
            'navCounts', 'classLabels', 'reprintable'
        ));
    }

    // -----------------------------------------------------------------
    // CLASSES — admin creates a class (grade + section + teacher) and
    // puts the student accounts into it. This replaces the old
    // "assign a teacher, wait for the teacher to enroll them" flow.
    // -----------------------------------------------------------------

    public function classes()
    {
        $students = User::where('role', 'student')->get()
            ->sort(function ($a, $b) {
                return [$this->gradeNumber($a->grade_level) ?? 99, (string) $a->last_name, (string) $a->first_name]
                    <=> [$this->gradeNumber($b->grade_level) ?? 99, (string) $b->last_name, (string) $b->first_name];
            })
            ->values();

        $parentNames = User::whereIn('id', $students->pluck('parent_id')->filter()->unique())
            ->get()
            ->mapWithKeys(fn ($p) => [$p->id => $p->name ?: trim($p->first_name . ' ' . $p->last_name)]);

        // Only approved teachers that the admin already gave a grade to
        // are offered when creating a class for that grade.
        $teachersByGrade = User::where('role', 'teacher')
            ->whereNotNull('email_verified_at')
            ->whereIn('grade_level', self::CLASS_GRADES)
            ->orderBy('name')
            ->get()
            ->groupBy('grade_level')
            ->map(fn ($group) => $group->map(fn ($t) => [
                'id'   => $t->id,
                'name' => $t->name ?: trim($t->first_name . ' ' . $t->last_name),
            ])->values());

        $classes = SchoolClass::with('students')
            ->whereIn('grade_level', self::CLASS_GRADES)
            ->get();

        $teacherNames = User::whereIn('id', $classes->pluck('teacher_id')->filter()->unique())
            ->get()
            ->mapWithKeys(fn ($t) => [$t->id => $t->name ?: trim($t->first_name . ' ' . $t->last_name)]);

        // $board['Grade 5']['Section 1'] = the class (or null if not created yet)
        $board = [];
        foreach (self::CLASS_GRADES as $grade) {
            $forGrade = $classes->where('grade_level', $grade);
            $sections = collect(self::CLASS_SECTIONS)
                ->merge($forGrade->pluck('section'))
                ->filter()
                ->unique()
                ->values();
            foreach ($sections as $section) {
                $board[$grade][$section] = $forGrade->firstWhere('section', $section);
            }
        }

        $classOptions = $classes
            ->sortBy(fn ($c) => [$this->gradeNumber($c->grade_level) ?? 99, (string) $c->section])
            ->map(fn ($c) => [
                'id'    => $c->id,
                'label' => $c->grade_level . ' — ' . $c->section,
                'gnum'  => $this->gradeNumber($c->grade_level),
            ])
            ->values();

        return view('admin.classes', [
            'grades'          => self::CLASS_GRADES,
            'sectionOptions'  => self::CLASS_SECTIONS,
            'board'           => $board,
            'teacherNames'    => $teacherNames,
            'teachersByGrade' => $teachersByGrade,
            'students'        => $students,
            'parentNames'     => $parentNames,
            'classLabels'     => $this->classLabelsByStudent(),
            'classOptions'    => $classOptions,
            'navCounts'       => $this->navCounts(),
        ]);
    }

    public function storeClass(Request $request)
    {
        $data = $request->validate([
            'grade_level' => ['required', Rule::in(self::CLASS_GRADES)],
            'section'     => ['required', Rule::in(self::CLASS_SECTIONS)],
            'teacher_id'  => 'required|integer',
        ], [
            'grade_level.required' => 'Please choose a grade level.',
            'section.required'     => 'Please choose a section.',
            'teacher_id.required'  => 'Please choose a teacher for this class.',
        ]);

        $teacher = User::where('id', $data['teacher_id'])
            ->where('role', 'teacher')
            ->whereNotNull('email_verified_at')
            ->first();

        if (!$teacher) {
            return back()->withInput()->withErrors([
                'teacher_id' => 'That teacher account is not approved or does not exist.',
            ]);
        }

        if ($teacher->grade_level !== $data['grade_level']) {
            return back()->withInput()->withErrors([
                'teacher_id' => "{$teacher->name} is assigned to " . ($teacher->grade_level ?: 'no grade')
                    . ", so they can't handle a {$data['grade_level']} class.",
            ]);
        }

        // The board shows one class per grade + section, so a second one
        // for the same section would just be confusing.
        if (SchoolClass::where('grade_level', $data['grade_level'])->where('section', $data['section'])->exists()) {
            return back()->withInput()->withErrors([
                'section' => "{$data['grade_level']} — {$data['section']} already has a class.",
            ]);
        }

        // Direct assignment (not mass-assignment) so this works regardless
        // of SchoolClass's $fillable — same approach used elsewhere here.
        $class = new SchoolClass();
        $class->name        = "{$data['grade_level']} - {$data['section']}";
        $class->teacher_id  = $teacher->id;
        $class->grade_level = $data['grade_level'];
        $class->section     = $data['section'];

        // The app has used both `class_code` and `code` for the class code;
        // fill whichever column(s) this database actually has.
        $table = $class->getTable();
        if (Schema::hasColumn($table, 'class_code')) {
            $class->class_code = $this->newClassCode($data['grade_level']);
        }
        if (Schema::hasColumn($table, 'code')) {
            $class->code = strtoupper(Str::random(6));
        }
        $class->save();

        return redirect()->route('admin.classes')->with(
            'success',
            "{$data['grade_level']} — {$data['section']} created with {$teacher->name} as the teacher."
        );
    }

    public function assignStudentsToClass(Request $request)
    {
        $data = $request->validate([
            'class_id'      => 'required|integer',
            'student_ids'   => 'required|array|min:1',
            'student_ids.*' => 'integer',
        ], [
            'class_id.required'    => 'Choose which section to add the students to.',
            'student_ids.required' => 'Tick at least one student first.',
            'student_ids.min'      => 'Tick at least one student first.',
        ]);

        $class    = SchoolClass::findOrFail($data['class_id']);
        $students = User::where('role', 'student')->whereIn('id', $data['student_ids'])->get();

        $classGrade = $this->gradeNumber($class->grade_level);
        $eligible   = $students->filter(fn ($s) => $this->gradeNumber($s->grade_level) === $classGrade)->values();
        $skipped    = $students->reject(fn ($s) => $this->gradeNumber($s->grade_level) === $classGrade)->values();

        if ($eligible->isEmpty()) {
            return back()->withErrors([
                'student_ids' => "None of the ticked students are {$class->grade_level}, so nobody was added to {$class->section}.",
            ]);
        }

        $ids       = $eligible->pluck('id')->all();
        $alreadyIn = $class->students()->whereIn('users.id', $ids)->pluck('users.id')->all();
        $moved     = 0;

        DB::transaction(function () use ($class, $eligible, $ids, &$moved) {
            // One section per student: adding to Section 2 moves them out
            // of Section 1 of the same grade.
            $otherClasses = SchoolClass::where('grade_level', $class->grade_level)
                ->where('id', '!=', $class->id)
                ->get();

            foreach ($otherClasses as $other) {
                $inOther = $other->students()->whereIn('users.id', $ids)->pluck('users.id')->all();
                if (!empty($inOther)) {
                    $other->students()->detach($inOther);
                    $moved += count($inOther);
                }
            }

            $class->students()->syncWithoutDetaching($ids);

            foreach ($eligible as $student) {
                $this->syncStudentEnrollment($student, $class->teacher_id, 'enrolled');
            }
        });

        $added   = count($ids) - count($alreadyIn);
        $message = "{$added} student(s) added to {$class->grade_level} — {$class->section}.";
        if ($moved > 0) {
            $message .= " {$moved} moved over from another {$class->grade_level} section.";
        }
        if (count($alreadyIn) > 0) {
            $message .= ' ' . count($alreadyIn) . ' already in this section.';
        }

        $redirect = back()->with('success', $message);
        if ($skipped->isNotEmpty()) {
            $redirect->withErrors([
                'student_ids' => 'Skipped (different grade level): ' . $skipped->pluck('name')->filter()->implode(', ') . '.',
            ]);
        }

        return $redirect;
    }

    public function removeStudentFromClass($classId, $studentId)
    {
        $class   = SchoolClass::findOrFail($classId);
        $student = User::where('role', 'student')->findOrFail($studentId);

        $class->students()->detach($student->id);

        // Only mark them unassigned if this was their last class.
        $stillInAClass = SchoolClass::whereHas('students', fn ($q) => $q->where('users.id', $student->id))->exists();
        if (!$stillInAClass) {
            $this->syncStudentEnrollment($student, null, 'unassigned');
        }

        return back()->with('success', "{$student->name} was removed from {$class->grade_level} — {$class->section}.");
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

                    $this->saveInitialPassword($user->id, $plainPassword);
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

    if ($user->role === 'student') {
        $this->saveInitialPassword($user->id, $newPassword);
    }

    return back()->with('success', "Password for {$user->name} has been reset to '{$newPassword}'.");
}

    // -----------------------------------------------------------------
    // REPRINT SLIPS — for students who still use their original password
    // -----------------------------------------------------------------

    /**
     * Rebuilds the printable slips for the ticked students. A slip is only
     * printed if the saved original password STILL matches the student's
     * current password hash — if they have changed it, the saved copy is
     * useless (and is deleted) and that student is skipped.
     */
    public function reprintCredentials(Request $request)
    {
        $data = $request->validate([
            'student_ids'   => 'required|array|min:1',
            'student_ids.*' => 'integer',
        ], [
            'student_ids.required' => 'Tick at least one student to print.',
            'student_ids.min'      => 'Tick at least one student to print.',
        ]);

        $students = User::where('role', 'student')
            ->whereIn('id', $data['student_ids'])
            ->orderBy('grade_level')
            ->orderBy('section')
            ->orderBy('last_name')
            ->get();

        $saved = DB::table('student_initial_passwords')
            ->whereIn('user_id', $students->pluck('id'))
            ->get()
            ->keyBy('user_id');

        $credentials = [];
        $errors      = [];

        foreach ($students as $s) {
            $name = $s->name ?: trim($s->first_name . ' ' . $s->last_name);
            $row  = $saved->get($s->id);

            if (!$row) {
                $errors[] = "{$name}: no saved password — use Reset password, then print again.";
                continue;
            }

            try {
                $plain = Crypt::decryptString($row->password);
            } catch (DecryptException $e) {
                $errors[] = "{$name}: saved password could not be read — use Reset password instead.";
                continue;
            }

            if (!Hash::check($plain, $s->password)) {
                DB::table('student_initial_passwords')->where('user_id', $s->id)->delete();
                $errors[] = "{$name} already changed their password — skipped.";
                continue;
            }

            $credentials[] = [
                'type'        => 'student',
                'name'        => $name,
                'login'       => $s->lrn,
                'login_label' => 'LRN',
                'password'    => $plain,
                'grade_level' => $s->grade_level,
                'section'     => $s->section,
            ];
        }

        return redirect()
            ->route('admin.credentials')
            ->with('credentials', $credentials)
            ->with('credential_errors', $errors);
    }

    /** Keeps an encrypted copy of a student's original password for reprinting. */
    private function saveInitialPassword(int $userId, string $plain): void
    {
        DB::table('student_initial_passwords')->updateOrInsert(
            ['user_id' => $userId],
            [
                'password'   => Crypt::encryptString($plain),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
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

    /** "Grade 5" / "5" / "grade5" -> 5, so grade values compare reliably. */
    private function gradeNumber($value): ?int
    {
        return preg_match('/\d+/', (string) $value, $m) ? (int) $m[0] : null;
    }

    private function navCounts(): array
    {
        return [
            'students' => User::where('role', 'student')->count(),
            'parents'  => User::where('role', 'parent')->count(),
            'teachers' => User::where('role', 'teacher')->count(),
        ];
    }

    /** student user id => "Grade 5 · Section 1", for every graded class. */
    private function classLabelsByStudent(): array
    {
        $labels  = [];
        $classes = SchoolClass::with('students')->whereIn('grade_level', self::CLASS_GRADES)->get();

        foreach ($classes as $class) {
            foreach ($class->students as $student) {
                $labels[$student->id] = trim($class->grade_level . ' · ' . $class->section);
            }
        }

        return $labels;
    }

    private function newClassCode(string $grade): string
    {
        $prefix = $this->gradeNumber($grade) === 5 ? 'G5' : 'G6';
        do {
            $code = $prefix . '-READ-' . random_int(1000, 9999);
        } while (SchoolClass::where('class_code', $code)->exists());

        return $code;
    }

    /** Keeps users + students rows in step (direct assignment, like reassignTeacher). */
    private function syncStudentEnrollment(User $student, $teacherId, string $status): void
    {
        $student->teacher_id        = $teacherId;
        $student->enrollment_status = $status;
        $student->save();

        $studentRow = Student::where('user_id', $student->id)->first();
        if ($studentRow) {
            $studentRow->teacher_id        = $teacherId;
            $studentRow->enrollment_status = $status;
            $studentRow->save();
        }
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