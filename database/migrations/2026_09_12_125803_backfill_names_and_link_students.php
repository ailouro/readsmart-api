<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    public function up(): void
    {
        // --- 1. Split existing users.name into first_name/last_name ---
        $users = DB::table('users')->whereNull('first_name')->get();
        foreach ($users as $user) {
            $parts = explode(' ', trim($user->name ?? ''), 2);
            $first = $parts[0] ?? '';
            $last = $parts[1] ?? '';
            // Single-word names (e.g. just "Admin") keep last_name blank
            // rather than guessing — safer than mis-splitting.
            DB::table('users')->where('id', $user->id)->update([
                'first_name' => $first,
                'last_name' => $last,
            ]);
        }

        // --- 2. Link old shadow `students` rows to real `users` accounts ---
        $shadowStudents = DB::table('students')->whereNull('user_id')->get();
        foreach ($shadowStudents as $student) {
            $parts = explode(' ', trim($student->name ?? ''), 2);
            $first = $parts[0] ?? '';
            $last = $parts[1] ?? '';

            // Create a real login-capable account for this student.
            // No email/lrn yet — an Admin/Teacher can assign one later
            // via the existing "create student" flow if this child
            // needs to log in directly.
            $newUserId = DB::table('users')->insertGetId([
                'name' => $student->name,
                'first_name' => $first,
                'last_name' => $last,
                'role' => 'student',
                'grade_level' => $student->grade_level,
                'password' => Hash::make('readsmart123'),
                'first_login' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('students')->where('id', $student->id)->update([
                'user_id' => $newUserId,
                'first_name' => $first,
                'last_name' => $last,
            ]);
        }
    }

    public function down(): void
    {
        // Data backfills are not meant to be reversed automatically —
        // reversing would delete real accounts. Handle manually if needed.
    }
};