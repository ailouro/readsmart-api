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

            // Isang query para sa lahat ng natapos na progress (iwas N+1)
            $allProgress = StudentProgress::whereIn('user_id', $students->pluck('id'))
                ->where('is_reading_completed', true)
                ->get()
                ->groupBy('user_id');

            foreach ($students as $student) {
                $class = $student->classes->first();

                // Get Pre-test Progress
                $preAttempts = ($allProgress[$student->id] ?? collect())->where('test_type', 'pre_test')->values();

                // Get Post-test Progress
                $postAttempts = ($allProgress[$student->id] ?? collect())->where('test_type', 'post_test')->values();

                // Compute Pre-test Averages
                [$preAcc, $preComp] = $this->pooled($preAttempts);
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
                [$postAcc, $postComp] = $this->pooled($postAttempts);
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

    /**
     * GET /api/teachers/{teacherId}/dashboard-summary?test_type=pre_test|post_test
     *
     * Ang hugis ng sagot ay yung binabasa ng Flutter (Students tab, Phil-IRI
     * panel, class chart): frustration/instructional/independent_count,
     * class_breakdown[], at students[].
     *
     * Level ng bawat estudyante ay POOLED (kabuuang tamang salita / kabuuang
     * salita; kabuuang tamang sagot / kabuuang tanong) -- parehong kuwenta
     * ng progress-detail, kaya tugma ang mga kahon, chart, roster at profile.
     * Natapos na story lang ang binibilang, at sinusunod ang test_type.
     */
    public function teacherDashboardSummary(Request $request, $teacherId)
    {
        try {
            $testType = $request->query('test_type', 'post_test');
            if (!in_array($testType, ['pre_test', 'post_test', 'all'], true)) {
                $testType = 'post_test';
            }

            $students = User::whereHas('classes', function ($q) use ($teacherId) {
                    $q->where('teacher_id', $teacherId);
                })
                ->with(['classes' => function ($q) use ($teacherId) {
                    $q->where('teacher_id', $teacherId);
                }])
                ->get();

            $progress = StudentProgress::whereIn('user_id', $students->pluck('id'))
                ->where('is_reading_completed', true)
                ->when($testType !== 'all', fn ($q) => $q->where('test_type', $testType))
                ->get()
                ->groupBy('user_id');

            $counts   = ['frustration' => 0, 'instructional' => 0, 'independent' => 0];
            $rows     = [];
            $perClass = []; // class_id => ['class' => model, 'levels' => [...], 'attempts' => Collection]

            foreach ($students as $student) {
                $attempts = $progress[$student->id] ?? collect();
                [$acc, $comp] = $this->pooled($attempts);

                $level = $wrLevel = $compLevel = null;
                if ($acc !== null && $comp !== null) {
                    $level     = strtolower($this->philIri->calculateReadingLevel($acc, $comp));
                    $wrLevel   = strtolower($this->philIri->wordReadingLevel($acc));
                    $compLevel = strtolower($this->philIri->comprehensionLevel($comp));
                    $counts[$level]++;
                }

                $first = $student->classes->first();

                $rows[] = [
                    'id'               => $student->id,
                    'name'             => $student->name ?? $student->username,
                    'username'         => $student->username ?? null,
                    'avatar'           => $student->avatar ?? null,
                    'lrn'              => $student->lrn ?? null,
                    'class_name'       => optional($first)->name,
                    'grade_level'      => $student->grade_level ?? optional($first)->grade_level,
                    'section'          => $student->section ?? optional($first)->section,
                    'class_id'         => optional($first)->id,
                    'classes'          => $student->classes->map(fn ($c) => [
                        'id'          => $c->id,
                        'name'        => $c->name,
                        'grade_level' => $c->grade_level ?? null,
                        'section'     => $c->section ?? null,
                    ])->values(),
                    'reading_level'    => $level,
                    'avg_accuracy'     => $acc,
                    'avg_comprehension'=> $comp,
                    'wr_level'         => $wrLevel,
                    'comp_level'       => $compLevel,
                    'stories_read'     => $attempts->count(),
                    // Totoong mic flag (galing sa Deepgram health ng app).
                    'mic_suspect'      => $attempts->contains(
                        fn ($a) => in_array($a->mic_status, ['no_audio', 'no_sound', 'no_transcript', 'unstable'], true)
                    ),
                    'mic_status'       => $attempts->pluck('mic_status')->filter()->unique()->values(),
                    // Pooled WPM: kabuuang tamang salita / kabuuang oras (hindi average ng WPM).
                    'avg_wpm'          => $this->pooledWpm($attempts),
                ];

                foreach ($student->classes as $c) {
                    $perClass[$c->id] ??= [
                        'class'    => $c,
                        'levels'   => ['frustration' => 0, 'instructional' => 0, 'independent' => 0],
                        'attempts' => collect(),
                    ];
                    if ($level) {
                        $perClass[$c->id]['levels'][$level]++;
                    }
                    $perClass[$c->id]['attempts'] = $perClass[$c->id]['attempts']->concat($attempts);
                }
            }

            $classBreakdown = [];
            foreach ($perClass as $classId => $info) {
                $c = $info['class'];
                [$cAcc, $cComp] = $this->pooled($info['attempts']);
                $grade   = $c->grade_level ?? null;
                $section = $c->section ?? null;

                $classBreakdown[] = [
                    'class_id'          => $classId,
                    'label'             => $grade
                        ? 'Grade ' . $grade . ($section ? ' - ' . $section : '')
                        : ($c->name ?? 'Class ' . $classId),
                    'grade_level'       => $grade,
                    'section'           => $section,
                    'frustration'       => $info['levels']['frustration'],
                    'instructional'     => $info['levels']['instructional'],
                    'independent'       => $info['levels']['independent'],
                    'avg_accuracy'      => $cAcc,
                    'avg_comprehension' => $cComp,
                ];
            }

            return response()->json([
                'success'            => true,
                'test_type'          => $testType,
                'frustration_count'  => $counts['frustration'],
                'instructional_count'=> $counts['instructional'],
                'independent_count'  => $counts['independent'],
                'class_breakdown'    => $classBreakdown,
                'students'           => $rows,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error'   => 'Failed to fetch dashboard summary',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POOLED na accuracy/comprehension (kabuuang tamang salita / kabuuang
     * salita, kabuuang tamang sagot / kabuuang tanong) -- parehong kuwenta
     * ng StudentProgressController::detail(), hindi average ng mga %.
     * Kung walang total_words (lumang records), average ang fallback.
     */
    private function pooled($attempts): array
    {
        if ($attempts->isEmpty()) {
            return [null, null];
        }

        $words   = (int) $attempts->sum('total_words');
        $miscues = (int) $attempts->sum('miscues_count');
        $quizC   = (int) $attempts->sum('quiz_score');
        $quizQ   = (int) $attempts->sum('total_questions');

        $acc = $words > 0
            ? round((max(0, $words - $miscues) / $words) * 100, 1)
            : round((float) $attempts->avg('oral_fluency_accuracy'), 1);
        $comp = $quizQ > 0
            ? round(($quizC / $quizQ) * 100)
            : round((float) $attempts->avg('comprehension_score_pct'));

        return [(float) $acc, (float) $comp];
    }
    /**
     * Pooled WPM. Hindi binibilang ang attempts na wala pang 5 segundo ang oras
     * (hindi makabuluhan ang WPM doon) o walang total_words.
     */
    private function pooledWpm($attempts): ?float
    {
        $valid = $attempts->filter(fn ($a) => (int) $a->time_on_task >= 5 && (int) $a->total_words > 0);
        $secs  = (int) $valid->sum('time_on_task');
        if ($secs <= 0) {
            return null;
        }
        $correct = (int) $valid->sum(fn ($a) => max(0, (int) $a->total_words - (int) $a->miscues_count));
        return round(($correct / $secs) * 60, 1);
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