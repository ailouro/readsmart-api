<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\Story;
use Illuminate\Http\Request;

class AssessmentController extends Controller
{
    /**
     * POST /api/classes/{classId}/assessments
     * Teacher assigns a pre-test to one student. Matches the payload sent
     * by the "Assign" dialog in class_dashboard_screen.dart.
     */
    public function store(Request $request, $classId)
    {
        $validated = $request->validate([
            'student_id'     => 'required|integer',
            'test_type'      => 'required|string|in:pre_test,post_test',
            'set_letter'     => 'required|string',
            'gst_raw'        => 'nullable|integer|min:0|max:20',
            'student_grade'  => 'required|integer',
        ]);

        // Normalize to a bare letter ('A'-'D') regardless of what the
        // client sent, so lookups against this row are consistent.
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

    /**
     * GET /api/classes/{classId}/assessments?student_id=X
     * Lists this student's assessment records in this class. Returns the
     * assessment's own id/columns directly (test_type, set_letter, gst_raw,
     * start_grade, independent_grade, ...) — this is what
     * class_dashboard_screen.dart reads into each `a` map.
     */
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

    /**
     * GET /api/assessment-passage?test_type=&set_letter=&grade=
     * Finds one Story matching the requested grade/set/type for
     * AssessmentFlowScreen._fetchPassage(). Accepts set_letter as either
     * a bare letter ('A') or the stored form ('Set A').
     */
    public function assessmentPassage(Request $request)
    {
        $validated = $request->validate([
            'test_type'  => 'required|string|in:pre_test,post_test',
            'set_letter' => 'required|string',
            'grade'      => 'required|integer|min:1|max:7',
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

    /**
     * POST /api/student/assessment-outcome
     * Saves the final Independent/Instructional/Frustration result once
     * PhilIriSession completes. Also auto-creates the matching post-test
     * assessment row after a pre-test finishes, seeded at the
     * instructional grade found — VERIFY this matches how your
     * PhilIriRules/PhilIriSession actually defines the post-test starting
     * point; adjust the fallback chain below if not.
     */
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

        // Auto-create the post-test row once the pre-test is done, so it
        // shows up in the class dashboard ready to take.
        if ($validated['test_type'] === 'pre_test') {
            $postTestStart = $validated['instructional_grade']
                ?? $validated['independent_grade']
                ?? $validated['start_grade'];

            Assessment::updateOrCreate(
                [
                    'class_id'   => $assessment->class_id,
                    'student_id' => $assessment->student_id,
                    'test_type'  => 'post_test',
                    'set_letter' => $letter,
                ],
                [
                    'student_grade' => $assessment->student_grade,
                    'start_grade'   => $postTestStart,
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