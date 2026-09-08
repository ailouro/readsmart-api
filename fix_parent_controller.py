import os
import re

controller_code = """<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SchoolClass;
use App\Models\Student;

class ParentController extends Controller
{
    public function dashboard($parentId)
    {
        $student = Student::where('parent_id', $parentId)->first();
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

        $student = Student::firstOrCreate(
            [
                'parent_id'   => $request->parent_id,
                'name'        => $request->student_name,
            ],
            [
                'grade_level' => $schoolClass->grade_level,
            ]
        );

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

api_path = "d:/xampp/htdocs/laravel/readsmart/backend/routes/api.php"
with open(api_path, "r", encoding="utf-8") as f:
    api_code = f.read()

# Add use statement if not present
if "use App\Http\Controllers\Api\ParentController;" not in api_code:
    api_code = api_code.replace("use Illuminate\Support\Facades\Route;", "use Illuminate\Support\Facades\Route;\nuse App\Http\Controllers\Api\ParentController;")

# Remove old enrollParent route
api_code = re.sub(r"Route::post\('/parents/enroll',\s*\[ClassEnrollmentController::class,\s*'enrollParent'\]\);\n?", "", api_code)

# Ensure new route is correct
if "Route::get('/parents/{parentId}/dashboard'" not in api_code:
    api_code = api_code.replace("Route::post('/parents/enroll', [ParentController::class, 'enroll']);", 
                                "Route::post('/parents/enroll', [ParentController::class, 'enroll']);\nRoute::get('/parents/{parentId}/dashboard', [ParentController::class, 'dashboard']);")

with open(api_path, "w", encoding="utf-8") as f:
    f.write(api_code)

print("ParentController created and routes updated.")
