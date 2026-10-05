<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\PhilIriService;
use App\Models\StudentProgress;
use App\Models\Mispronunciation;
use App\Models\User;

class AnalyticsController extends Controller
{
    public function __construct(private PhilIriService $philIri) {}

    /**
     * Endpoint 1: Dashboard Summary
     *
     * Isang bata = isang bilang. Para sa napiling test_type (pre_test / post_test),
     * inaaverage ang oral accuracy at comprehension % ng lahat ng natapos na
     * stories ng bata, tapos ipinapasa sa PhilIriService para makuha ang level.
     */
    public function dashboardSummary(Request $request, $teacherId)
    {
        try {
            $testType = $request->query('test_type', 'post_test');

            $byStudent = StudentProgress::with(['student.classes' => function ($query) use ($teacherId) {
                    $query->where('teacher_id', $teacherId);
                }])
                ->whereHas('student.classes', function ($query) use ($teacherId) {
                    $query->where('teacher_id', $teacherId);
                })
                ->where('test_type', $testType)
                ->where('is_reading_completed', true)
                ->whereNotNull('reading_level')
                ->get()
                ->groupBy('user_id');

            $counts = ['frustration' => 0, 'instructional' => 0, 'independent' => 0];

            // Per class + section breakdown (e.g. "Grade 5 - Magsaysay").
            // Ang batang nasa higit sa isang class ay lalabas sa bawat class,
            // pero isang beses lang sa overall total.
            $classBreakdown = [];

            foreach ($byStudent as $attempts) {
                $student = $attempts->first()->student;
                if (!$student) {
                    continue;
                }

                $level = strtolower($this->philIri->calculateReadingLevel(
                    (float) $attempts->avg('oral_fluency_accuracy'),
                    (float) $attempts->avg('comprehension_score_pct')
                ));

                $counts[$level]++;

                foreach ($student->classes as $class) {
                    $key = $class->id;
                    if (!isset($classBreakdown[$key])) {
                        $classBreakdown[$key] = [
                            'class_id' => $class->id,
                            'grade_level' => $class->grade_level,
                            'section' => $class->section,
                            'label' => trim('Grade ' . $class->grade_level . ' - ' . $class->section, ' -'),
                            'frustration' => 0,
                            'instructional' => 0,
                            'independent' => 0,
                            'total' => 0,
                        ];
                    }
                    $classBreakdown[$key][$level]++;
                    $classBreakdown[$key]['total']++;
                }
            }

            // Roster para sa "Learner's Individual Record Card" / "Learners' Records".
            $students = User::whereHas('classes', function ($query) use ($teacherId) {
                    $query->where('teacher_id', $teacherId);
                })
                ->with(['classes' => function ($query) use ($teacherId) {
                    $query->where('teacher_id', $teacherId);
                }])
                ->get()
                ->map(function ($student) {
                    $class = $student->classes->first();
                    return [
                        'id' => $student->id,
                        'name' => $student->name,
                        'lrn' => $student->lrn ?? null,
                        'grade_level' => $student->grade_level ?? optional($class)->grade_level,
                        'section' => $student->section ?? optional($class)->section,
                    ];
                })
                ->values();

            return response()->json([
                'test_type' => $testType,
                'frustration_count' => $counts['frustration'],
                'instructional_count' => $counts['instructional'],
                'independent_count' => $counts['independent'],
                'class_breakdown' => array_values($classBreakdown),
                'students' => $students,
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch dashboard summary', 'details' => $e->getMessage()], 500);
        }
    }

    /**
     * Endpoint 2: Mispronunciations
     */
    public function mispronunciations($teacherId)
    {
        try {
            // Because you log every mistake individually, we use DB::raw to group them
            // by word, student, and story, and count how many times it occurred.
            $logs = Mispronunciation::with(['student'])
                ->whereHas('student.classes', function ($query) use ($teacherId) {
                    $query->where('teacher_id', $teacherId);
                })
                ->select(
                    'word',
                    'student_id',
                    'story_id',
                    'story_title',
                    DB::raw('count(*) as total_attempts')
                )
                ->groupBy('word', 'student_id', 'story_id', 'story_title')
                ->orderByDesc('total_attempts')
                ->take(20)
                ->get();

            $formattedData = $logs->map(function ($log) {
                return [
                    'word' => $log->word,
                    'student_id' => $log->student_id,
                    'story_id' => $log->story_id,
                    'total_attempts' => $log->total_attempts,
                    'student' => [
                        'id' => $log->student_id,
                        'name' => $log->student ? ($log->student->name ?? $log->student->username ?? 'Unknown') : 'Unknown',
                    ],
                    'story' => [
                        'id' => $log->story_id,
                        'title' => $log->story_title,
                    ]
                ];
            });

            return response()->json([
                'data' => $formattedData
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch mispronunciation data', 'details' => $e->getMessage()], 500);
        }
    }
}