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
            'student_grade'  => 'required|integer|min:2|max:7',
            'start_grade'    => 'nullable|integer|min:2|max:7',
        ]);

        // gst_raw drives the pre-test's starting grade (Stage 2, Step 1);
        // start_grade is where a post-test begins instead, since there's
        // no GST for a post-test. Each is required for its own test_type
        // so a half-filled assignment never gets silently saved.
        if ($validated['test_type'] === 'pre_test' && $request->input('gst_raw') === null) {
            return response()->json([
                'message' => 'gst_raw is required to assign a pre-test.',
            ], 422);
        }
        if ($validated['test_type'] === 'post_test' && $request->input('start_grade') === null) {
            return response()->json([
                'message' => 'start_grade is required to assign a post-test.',
            ], 422);
        }

        $setLetter = strtoupper(trim(str_ireplace('Set', '', $validated['set_letter'])));

        // set_letter is intentionally NOT part of the match key below.
        // Every part of the app (progress counts, the Pre-Test-done gate,
        // _assessmentFor on the frontend) assumes one row per
        // (class, student, test_type). Matching on set_letter too used to
        // mean a reassignment with a different set created a second row
        // instead of replacing the first, leaving an orphaned, uncompletable
        // row behind that silently inflated totals and could block
        // Post-Test from ever unlocking.
        $assessment = Assessment::updateOrCreate(
            [
                'class_id'   => $classId,
                'student_id' => $validated['student_id'],
                'test_type'  => $validated['test_type'],
            ],
            [
                'set_letter'    => $setLetter,
                'gst_raw'       => $validated['gst_raw'] ?? null,
                'student_grade' => $validated['student_grade'],
                'start_grade'   => $validated['start_grade'] ?? null,
                'status'        => 'assigned',
                // A (re)assignment is always a fresh attempt. Without this,
                // reassigning a class/student/test_type/set combo that was
                // already completed before would leave the old outcome
                // (independent/instructional/frustration grades, session
                // data) sitting on a row now marked 'assigned' again.
                'independent_grade'   => null,
                'instructional_grade' => null,
                'frustration_grade'   => null,
                'below_range'         => false,
                'above_range'         => false,
                'session_data'        => null,
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


    /**
     * Class-wide random assignment from the Stories tab. Every student in
     * $request->student_ids gets a randomly picked Set (A-D, independently
     * per student — an uneven split is expected, this is a real shuffle,
     * not a balanced round-robin).
     *
     * Deliberately skips GST: this path is for a fixed research sample
     * where every student in the class starts at the class's own grade
     * level, not Stage 2's per-student GST-computed starting grade. That's
     * a product decision for this flow only — the per-student
     * store() endpoint above is untouched and still requires gst_raw for
     * a pre-test exactly as before.
     */
    public function bulkStore(Request $request, $classId)
    {
        $validated = $request->validate([
            'test_type'      => 'required|string|in:pre_test,post_test',
            'student_ids'    => 'required|array|min:1',
            'student_ids.*'  => 'integer',
            'student_grade'  => 'required|integer|min:2|max:7',
        ]);

        $sets = ['A', 'B', 'C', 'D'];
        $assignments = [];

        \DB::transaction(function () use ($validated, $classId, $sets, &$assignments) {
            foreach ($validated['student_ids'] as $studentId) {
                $letter = $sets[array_rand($sets)];

                // Same match key as store(): one row per
                // (class_id, student_id, test_type), so re-running this on
                // a class that already has assignments reshuffles them
                // rather than piling up duplicates.
                $assignments[] = Assessment::updateOrCreate(
                    [
                        'class_id'   => $classId,
                        'student_id' => $studentId,
                        'test_type'  => $validated['test_type'],
                    ],
                    [
                        'set_letter'    => $letter,
                        'gst_raw'       => null,
                        'student_grade' => $validated['student_grade'],
                        'start_grade'   => $validated['student_grade'],
                        'status'        => 'assigned',
                        'independent_grade'   => null,
                        'instructional_grade' => null,
                        'frustration_grade'   => null,
                        'below_range'         => false,
                        'above_range'         => false,
                        'session_data'        => null,
                    ]
                );
            }
        });

        return response()->json([
            'success'     => true,
            'message'     => 'Randomly assigned '.count($assignments).' student(s).',
            'assessments' => $assignments,
        ], 201);
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
            // set_letter kept out of the match key here too, for the same
            // reason as store(): this must land on the student's single
            // post_test row, not spawn a second one alongside any post_test
            // a teacher may have already assigned by hand.
            Assessment::updateOrCreate(
                [
                    'class_id'   => $assessment->class_id,
                    'student_id' => $assessment->student_id,
                    'test_type'  => 'post_test',
                ],
                [
                    'set_letter'          => $letter,
                    'student_grade'       => $assessment->student_grade,
                    'start_grade'         => $validated['start_grade'],
                    'status'              => 'assigned',
                    // Same reasoning as store(): if a post-test already
                    // sat on this exact slot from an earlier cycle, don't
                    // let its old outcome linger under the fresh 'assigned'
                    // status this just set.
                    'independent_grade'   => null,
                    'instructional_grade' => null,
                    'frustration_grade'   => null,
                    'below_range'         => false,
                    'above_range'         => false,
                    'session_data'        => null,
                ]
            );
        }

        return response()->json([
            'success'    => true,
            'assessment' => $assessment->fresh(),
        ]);
    }
}