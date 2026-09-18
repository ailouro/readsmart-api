<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Support\Str;

class ClassController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'name'       => 'required|string',
            'section'    => 'nullable|string',
            'grade_level' => 'required',
            'teacher_id' => 'nullable|exists:users,id',
        ]);

        $resolvedTeacherId = $request->teacher_id ?? auth('api')->id();

        if (!$resolvedTeacherId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Could not determine the teacher creating this class. Please make sure you are logged in.',
            ], 401);
        }

        $class = SchoolClass::create([
            'name'       => $request->name,
            'section'    => $request->section,
            'teacher_id' => $resolvedTeacherId,
            'code'       => strtoupper(Str::random(6)),
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Class created successfully!',
            'class'   => $class,
        ], 201);
    }

    public function getClassStories($classId)
    {
        $class = SchoolClass::with(['stories.pages', 'stories.quiz'])->findOrFail($classId);

        return response()->json([
            'success' => true,
            'stories' => $class->stories
        ], 200);
    }

    public function assignStory(Request $request, $id)
    {
        $validated = $request->validate([
            'story_id' => 'required|array',
            'story_id.*' => 'exists:stories,id',
            'test_type' => 'nullable|string|in:pre_test,post_test'
        ]);

        $class = SchoolClass::findOrFail($id);

        $testType = $validated['test_type'] ?? 'post_test';
        $syncData = [];
        foreach ($validated['story_id'] as $storyId) {
            $syncData[$storyId] = ['test_type' => $testType];
        }

        $class->stories()->syncWithoutDetaching($syncData);

        return response()->json([
            'status' => 'success',
            'message' => 'Stories assigned successfully.',
        ], 200);
    }

    public function show($id)
    {
        $class = SchoolClass::with(['stories.pages', 'stories.quiz', 'students'])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $class
        ]);
    }

    // --- BULK ADD STUDENT METHODS ---

    public function getAvailableStudents($id)
    {
        $class = SchoolClass::findOrFail($id);

        $enrolledIds = $class->students()->pluck('students.id')->toArray();

        $query = Student::where('grade_level', $class->grade_level);
        
        if (!empty($class->section) && $class->section !== 'N/A') {
            $query->where('section', $class->section);
        }

        $available = $query->whereNotIn('id', $enrolledIds)
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'name', 'lrn']);

        return response()->json([
            'status' => 'success',
            'data' => $available
        ]);
    }

    public function bulkAddStudents(Request $request, $id)
    {
        $request->validate([
            'student_ids' => 'required|array',
            'student_ids.*' => 'exists:students,id'
        ]);

        $class = SchoolClass::findOrFail($id);
        
        $class->students()->syncWithoutDetaching($request->student_ids);

        return response()->json([
            'status' => 'success',
            'message' => 'Students successfully added to the class!'
        ]);
    }

    public function destroy($id)
{
    $class = SchoolClass::find($id); 

    if (!$class) {
        return response()->json(['message' => 'Class not found'], 404);
    }

    $class->delete();

    return response()->json(['message' => 'Class deleted successfully'], 200);
}
}