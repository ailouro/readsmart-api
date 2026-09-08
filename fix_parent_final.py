import os
import glob
import re

# Find the migration file
migrations = glob.glob("d:/xampp/htdocs/laravel/readsmart/backend/database/migrations/*_add_parent_id_to_users_table.php")
if migrations:
    mig_file = migrations[0]
    mig_code = """<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->constrained('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn('parent_id');
        });
    }
};
"""
    with open(mig_file, "w", encoding="utf-8") as f:
        f.write(mig_code)

# Update ParentController
controller_code = """<?php

namespace App\\Http\\Controllers\\Api;

use App\\Http\\Controllers\\Controller;
use Illuminate\\Http\\Request;
use App\\Models\\SchoolClass;
use App\\Models\\User;

class ParentController extends Controller
{
    public function dashboard($parentId)
    {
        // Find the student linked to this parent
        $student = User::where('parent_id', $parentId)->where('role', 'student')->first();
        if (!$student) {
            return response()->json(null, 404);
        }
        $class = $student->classes()->first();
        if (!$class) {
            return response()->json(null, 404);
        }
        
        return response()->json([
            'class_name'   => $class->name,
            'class_code'   => $class->class_code,
            'student_name' => $student->name,
            'student_id'   => $student->id,
            'grade_level'  => $class->grade_level,
        ], 200);
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

        return response()->json([
            'message'      => 'Student successfully enrolled in class!',
            'student_name' => $student->name,
            'student_id'   => $student->id,
            'class_name'   => $schoolClass->name,
            'grade_level'  => $schoolClass->grade_level,
            'class_code'   => $schoolClass->class_code,
        ], 200);
    }
}
"""
with open("d:/xampp/htdocs/laravel/readsmart/backend/app/Http/Controllers/Api/ParentController.php", "w", encoding="utf-8") as f:
    f.write(controller_code)

# Add parent_id to User fillable
user_model_path = "d:/xampp/htdocs/laravel/readsmart/backend/app/Models/User.php"
with open(user_model_path, "r", encoding="utf-8") as f:
    user_code = f.read()

if "'parent_id'" not in user_code:
    user_code = user_code.replace("'name',", "'name',\n        'parent_id',")
    with open(user_model_path, "w", encoding="utf-8") as f:
        f.write(user_code)

print("Migration, User model, and ParentController updated successfully.")
