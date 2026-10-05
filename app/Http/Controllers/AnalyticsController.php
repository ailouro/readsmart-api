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
     * Endpoint para sa Comparative Dashboard (Pre-test vs Post-test Chart Data)
     */
    public function dashboardSummary(Request $request, $teacherId)
    {
        try {
            // Kuhanin ang lahat ng mag-aaral sa ilalim ng guro
            $students = User::whereHas('classes', function ($query) use ($teacherId) {
                $query->where('teacher_id', $teacherId);
            })
            ->with(['classes' => function ($query) use ($teacherId) {
                $query->where('teacher_id', $teacherId);
            }])
            ->get();

            $comparativeData = [];
            
            $overallPreStats = ['frustration' => 0, 'instructional' => 0, 'independent' => 0, 'acc_sum' => 0, 'comp_sum' => 0, 'count' => 0];
            $overallPostStats = ['frustration' => 0, 'instructional' => 0, 'independent' => 0, 'acc_sum' => 0, 'comp_sum' => 0, 'count' => 0];

            foreach ($students as $student) {
                $class = $student->classes->first();

                // Get Pre-test Progress
                $preAttempts = StudentProgress::where('user_id', $student->id)
                    ->where('test_type', 'pre_test')
                    ->where('is_reading_completed', true)
                    ->get();

                // Get Post-test Progress
                $postAttempts = StudentProgress::where('user_id', $student->id)
                    ->where('test_type', 'post_test')
                    ->where('is_reading_completed', true)
                    ->get();

                // Compute Pre-test Averages
                $preAcc  = $preAttempts->count() > 0 ? (float) $preAttempts->avg('oral_fluency_accuracy') : null;
                $preComp = $preAttempts->count() > 0 ? (float) $preAttempts->avg('comprehension_score_pct') : null;
                $preLevel = ($preAcc !== null && $preComp !== null) 
                    ? strtolower($this->philIri->calculateReadingLevel($preAcc, $preComp)) 
                    : null;

                if ($preLevel) {
                    $overallPreStats[$preLevel]++;
                    $overallPreStats['acc_sum'] += $preAcc;
                    $overallPreStats['comp_sum'] += $preComp;
                    $overallPreStats['count']++;
                }

                // Compute Post-test Averages
                $postAcc  = $postAttempts->count() > 0 ? (float) $postAttempts->avg('oral_fluency_accuracy') : null;
                $postComp = $postAttempts->count() > 0 ? (float) $postAttempts->avg('comprehension_score_pct') : null;
                $postLevel = ($postAcc !== null && $postComp !== null) 
                    ? strtolower($this->philIri->calculateReadingLevel($postAcc, $postComp)) 
                    : null;

                if ($postLevel) {
                    $overallPostStats[$postLevel]++;
                    $overallPostStats['acc_sum'] += $postAcc;
                    $overallPostStats['comp_sum'] += $postComp;
                    $overallPostStats['count']++;
                }

                $comparativeData[] = [
                    'student_id'     => $student->id,
                    'name'           => $student->name,
                    'lrn'            => $student->lrn ?? null,
                    'grade_level'    => $student->grade_level ?? optional($class)->grade_level,
                    'section'        => $student->section ?? optional($class)->section,
                    
                    // Pre-test Metrics
                    'pre_test' => [
                        'accuracy'      => $preAcc !== null ? round($preAcc, 1) : null,
                        'comprehension' => $preComp !== null ? (int) round($preComp) : null,
                        'reading_level' => $preLevel,
                        'stories_read'  => $preAttempts->count(),
                    ],

                    // Post-test Metrics
                    'post_test' => [
                        'accuracy'      => $postAcc !== null ? round($postAcc, 1) : null,
                        'comprehension' => $postComp !== null ? (int) round($postComp) : null,
                        'reading_level' => $postLevel,
                        'stories_read'  => $postAttempts->count(),
                    ],

                    // Learning Gains
                    'accuracy_gain'      => ($preAcc !== null && $postAcc !== null) ? round($postAcc - $preAcc, 1) : 0,
                    'comprehension_gain' => ($preComp !== null && $postComp !== null) ? round($postComp - $preComp, 1) : 0,
                ];
            }

            // Chart Aggregates (Panlagay sa Pre-test vs Post-test Bar/Line Chart)
            $chartSummary = [
                'pre_test' => [
                    'frustration_count'  => $overallPreStats['frustration'],
                    'instructional_count'=> $overallPreStats['instructional'],
                    'independent_count'  => $overallPreStats['independent'],
                    'avg_accuracy'       => $overallPreStats['count'] > 0 ? round($overallPreStats['acc_sum'] / $overallPreStats['count'], 1) : 0,
                    'avg_comprehension'  => $overallPreStats['count'] > 0 ? (int) round($overallPreStats['comp_sum'] / $overallPreStats['count']) : 0,
                ],
                'post_test' => [
                    'frustration_count'  => $overallPostStats['frustration'],
                    'instructional_count'=> $overallPostStats['instructional'],
                    'independent_count'  => $overallPostStats['independent'],
                    'avg_accuracy'       => $overallPostStats['count'] > 0 ? round($overallPostStats['acc_sum'] / $overallPostStats['count'], 1) : 0,
                    'avg_comprehension'  => $overallPostStats['count'] > 0 ? (int) round($overallPostStats['comp_sum'] / $overallPostStats['count']) : 0,
                ]
            ];

            return response()->json([
                'success'       => true,
                'chart_summary' => $chartSummary,
                'students'      => $comparativeData,
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch comparative analytics', 'details' => $e->getMessage()], 500);
        }
    }

    public function mispronunciations($teacherId)
    {
        try {
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
                    'word'           => $log->word,
                    'student_id'     => $log->student_id,
                    'story_id'       => $log->story_id,
                    'total_attempts' => $log->total_attempts,
                    'student'        => [
                        'id'   => $log->student_id,
                        'name' => $log->student ? ($log->student->name ?? $log->student->username ?? 'Unknown') : 'Unknown',
                    ],
                    'story'          => [
                        'id'    => $log->story_id,
                        'title' => $log->story_title,
                    ]
                ];
            });

            return response()->json(['data' => $formattedData], 200);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch mispronunciation data', 'details' => $e->getMessage()], 500);
        }
    }
}