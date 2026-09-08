<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SchoolClass; // O kung ano man ang Model name mo
use Illuminate\Support\Str;

class ClassController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'name'       => 'required|string',
            'section'    => 'nullable|string',
            'grade_level' => 'required',
            'teacher_id' => 'nullable|exists:users,id', // o 'teachers,id'
        ]);

        // 🛠️ FIX: auth()->id() checks the default ('web') guard, which is
        // always null for JWT-authenticated API requests. Use the 'api'
        // guard explicitly so the logged-in teacher's real ID is used
        // instead of silently falling back to 1.
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
        'code'       => strtoupper(Str::random(6)), // Auto-generate ng 6-character class code (hal. X7K2P9)
    ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Class created successfully!',
            'class'   => $class,
        ], 201);
    }

    // Get all stories assigned to a specific class
    public function getClassStories($classId)
    {
        $class = SchoolClass::with(['stories.pages', 'stories.quiz'])->findOrFail($classId);

        return response()->json([
            'success' => true,
            'stories' => $class->stories
        ], 200);
    }

// Assign an existing library story to a class
public function assignStory(Request $request, $id)
    {
        $validated = $request->validate([
            'story_id' => 'required|array',
            'story_id.*' => 'exists:stories,id',
            'test_type' => 'nullable|string|in:pre_test,post_test'
        ]);

        $class = SchoolClass::findOrFail($id);

        // Attach each story with its test_type
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
    // Make sure 'students' relationship is included
    $class = SchoolClass::with(['stories.pages', 'stories.quiz', 'students'])->findOrFail($id);

    return response()->json([
        'status' => 'success',
        'data' => $class
    ]);
}

}