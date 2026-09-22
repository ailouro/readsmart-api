<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\Story;
use Illuminate\Http\Request;

class AssessmentController extends Controller
{
   
    public function store(Request $request, $classId)
    {
        $validated = $request->validate([
            'student_id'     => 'required|integer',
            'test_type'      => 'required|string|in:pre_test,post_test',
            'set_letter'     => 'required|string',
            'gst_raw'        => 'nullable|integer|min:0|max:20',
            'student_grade'  => 'required|integer',
        ]);

        
        $setLetter = strtoupper(trim(str_ireplace('Set', '', $validated['set_letter'])));

        $assessment = Assessment::updateOrCreate(
            [
                'class_id'   => $classId,
                'student_id' => $validated['student_id'],
                'test_type'  => $validated['test_type'],
                'set_letter' => $setLetter,
            ],
            [
                'gst_raw'       => $validated['gst_raw'] ?? null,
                'student_grade' => $validated['student_grade'],
                'status'        => 'assigned',
            ]
        );

        return response()->json([
            'success'    => true,
            'message'    => 'Assessment assigned successfully!',
            'assessment' => $assessment,
        ], 201);
    }


    public function index(Request $request, $classId)
    {
        $studentId = $request->query('student_id');

        $query = Assessment::where('class_id', $classId);
        if ($studentId) {
            $query->where('student_id', $studentId);
        }

        return response()->json([
            'assessments' => $query->orderBy('created_at')->get(),
        ]);
    }


    public function assessmentPassage(Request $request)
    {
        $validated = $request->validate([
            'test_type'  => 'required|string|in:pre_test,post_test',
            'set_letter' => 'required|string',
            'grade'      => 'required|integer|min:2|max:7',
        ]);

        $letter = strtoupper(trim(str_ireplace('Set', '', $validated['set_letter'])));
        $gradeLabel = 'Grade ' . $validated['grade'];

        $story = Story::with('pages')
            ->where('story_type', $validated['test_type'])
            ->where('grade_level', $gradeLabel)
            ->where(function ($q) use ($letter) {
                $q->where('set_letter', $letter)
                  ->orWhere('set_letter', 'Set ' . $letter);
            })
            ->first();

        if (!$story) {
            return response()->json([
                'message' => "No {$validated['test_type']} story found for {$gradeLabel}, Set {$letter}.",
            ], 404);
        }

        return response()->json(['story' => $story]);
    }


    public function outcome(Request $request)
    {
        $validated = $request->validate([
            'student_id'           => 'required|integer',
            'test_type'            => 'required|string|in:pre_test,post_test',
            'set_letter'           => 'required|string',
            'start_grade'          => 'required|integer',
            'independent_grade'    => 'nullable|integer',
            'instructional_grade'  => 'nullable|integer',
            'frustration_grade'    => 'nullable|integer',
            'below_range'          => 'nullable|boolean',
            'above_range'          => 'nullable|boolean',
            'session'              => 'nullable|array',
        ]);

        $letter = strtoupper(trim(str_ireplace('Set', '', $validated['set_letter'])));

        $assessment = Assessment::where('student_id', $validated['student_id'])
            ->where('test_type', $validated['test_type'])
            ->where('set_letter', $letter)
            ->latest('created_at')
            ->first();

        if (!$assessment) {
            return response()->json(['message' => 'Matching assessment assignment not found.'], 404);
        }

        $assessment->update([
            'start_grade'         => $validated['start_grade'],
            'independent_grade'   => $validated['independent_grade'] ?? null,
            'instructional_grade' => $validated['instructional_grade'] ?? null,
            'frustration_grade'   => $validated['frustration_grade'] ?? null,
            'below_range'         => $validated['below_range'] ?? false,
            'above_range'         => $validated['above_range'] ?? false,
            'session_data'        => $validated['session'] ?? null,
            'status'              => 'completed',
        ]);

        if ($validated['test_type'] === 'pre_test') {
            Assessment::updateOrCreate(
                [
                    'class_id'   => $assessment->class_id,
                    'student_id' => $assessment->student_id,
                    'test_type'  => 'post_test',
                    'set_letter' => $letter,
                ],
                [
                    'student_grade' => $assessment->student_grade,
                    'start_grade'   => $validated['start_grade'],
                    'status'        => 'assigned',
                ]
            );
        }

        return response()->json([
            'success'    => true,
            'assessment' => $assessment->fresh(),
        ]);
    }
}