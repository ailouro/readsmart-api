<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveProgressRequest;
use App\Models\StudentProgress;
use App\Models\SelfCorrection;
use App\Services\PhilIriService;
use Illuminate\Http\Request;
use App\Models\SchoolClass;
use Illuminate\Support\Facades\DB;

class StudentProgressController extends Controller
{
    protected PhilIriService $philIriService;

    public function __construct(PhilIriService $philIriService)
    {
        $this->philIriService = $philIriService;
    }

    public function getCompletedStories($studentId)
    {
        try {
            return response()->json([
                'success' => true,
                'message' => 'Completed stories retrieved successfully.',
                'data'    => StudentProgress::completedStoriesFor($studentId),
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch completed stories.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/student/{studentId}/progress-detail
     *
     * Isang record bawat natapos na story (may bilang ng salita, miscues,
     * tamang salita, quiz, WPM, oras) + kabuuang total ng Pre-Test at
     * Post-Test para sa paghahambing. Ang totals ay POOLED (kabuuang tamang
     * salita ÷ kabuuang salita), hindi average ng mga %.
     */
    public function detail($studentId)
    {
        try {
            $rows = StudentProgress::with('story')
                ->where('user_id', $studentId)
                ->where('is_reading_completed', true)
                ->orderBy('completed_at')
                ->get();

            $records = $rows->map(function ($p) {
                $total   = (int) ($p->total_words ?? 0);
                $miscues = (int) ($p->miscues_count ?? 0);
                $wordPct = $p->word_reading_score_pct ?? $p->oral_fluency_accuracy;

                return [
                    'id'                      => $p->id,
                    'story_id'                => $p->story_id,
                    'story_title'             => optional($p->story)->title ?? 'Untitled story',
                    'test_type'               => $p->test_type,
                    'total_words'             => $total,
                    'miscues_count'           => $miscues,
                    'correct_words_count'     => max(0, $total - $miscues),
                    'oral_fluency_accuracy'   => $wordPct,
                    'word_reading_score_pct'  => $wordPct,
                    'quiz_score'              => (int) ($p->quiz_score ?? 0),
                    'total_questions'         => (int) ($p->total_questions ?? 0),
                    'comprehension_score_pct' => $p->comprehension_score_pct,
                    'wpm'                     => $p->wpm,
                    'time_on_task'            => (int) ($p->time_on_task ?? 0),
                    'reading_level'           => $p->reading_level,
                    'date_completed'          => $p->completed_at ?? $p->updated_at,
                ];
            })->values();

            $totals = [];
            foreach (['pre_test', 'post_test'] as $type) {
                $set = $records->where('test_type', $type);

                if ($set->isEmpty()) {
                    $totals[$type] = ['stories' => 0];
                    continue;
                }

                $words   = (int) $set->sum('total_words');
                $miscues = (int) $set->sum('miscues_count');
                $correct = max(0, $words - $miscues);
                $quizC   = (int) $set->sum('quiz_score');
                $quizQ   = (int) $set->sum('total_questions');

                // Kung may lumang records na walang total_words, average ang fallback.
                $wordPct = $words > 0
                    ? round(($correct / $words) * 100, 1)
                    : round((float) $set->avg('oral_fluency_accuracy'), 1);
                $compPct = $quizQ > 0
                    ? (int) round(($quizC / $quizQ) * 100)
                    : (int) round((float) $set->avg('comprehension_score_pct'));

                $totals[$type] = [
                    'stories'        => $set->count(),
                    'total_words'    => $words,
                    'miscues'        => $miscues,
                    'correct_words'  => $correct,
                    'word_pct'       => $wordPct,
                    'quiz_correct'   => $quizC,
                    'quiz_questions' => $quizQ,
                    'comp_pct'       => $compPct,
                    'level'          => strtolower($this->philIriService->calculateReadingLevel($wordPct, $compPct)),
                ];
            }

            return response()->json([
                'success' => true,
                'data'    => $records,
                'totals'  => $totals,
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch progress detail.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function saveProgress(SaveProgressRequest $request)
    {
        $validated = $request->validated();

        // 1. Kuhanin ang kabuuang salita sa kwento mula sa audio_scripts
        $totalWords = $this->getStoryTotalWords($validated['story_id']);
        
        // 2. Kuhanin ang bilang ng miscues batay sa accuracy % o ibinigay ng request
        $accuracy = (float) $validated['oral_fluency_accuracy'];
        $miscues  = $request->input('miscues_count', max(0, (int) round($totalWords * (1 - ($accuracy / 100)))));

        // 3. Compute detailed Phil-IRI metrics
        $breakdown = $this->philIriService->getDetailedBreakdown(
            $totalWords,
            $miscues,
            (int) $validated['quiz_score'],
            (int) $validated['total_questions'],
            (int) $validated['time_on_task']
        );

        // 4. I-save sa database
        $progress = StudentProgress::updateOrCreate(
            [
                'user_id'   => $validated['user_id'],
                'story_id'  => $validated['story_id'],
                'test_type' => $validated['test_type'], 
            ],
            [
                'total_words'             => $breakdown['total_words'],
                'miscues_count'           => $breakdown['miscues_count'],
                'quiz_score'              => $breakdown['quiz_score'],
                'total_questions'         => $breakdown['total_questions'],
                'oral_fluency_accuracy'   => $breakdown['word_reading_score_pct'],
                'word_reading_score_pct'  => $breakdown['word_reading_score_pct'],
                'time_on_task'            => $breakdown['time_on_task_seconds'],
                'wpm'                     => $breakdown['wpm'], 
                'comprehension_score_pct' => $breakdown['comprehension_score_pct'],
                'reading_level'           => $breakdown['final_reading_level'],
                'is_reading_completed'   => true,
            ]
        );

        foreach ($validated['self_corrected_words'] ?? [] as $w) {
            SelfCorrection::create([
                'student_id'     => $validated['user_id'],
                'story_id'       => $validated['story_id'],
                'word'           => strtolower($w['word']),
                'total_attempts' => $w['total_attempts'] ?? 2,
            ]);
        }

        return response()->json([
            'success'   => true,
            'message'   => 'Progress saved successfully',
            'data'      => $progress,
            'breakdown' => $breakdown // Ibibigay sa mobile app para sa result screen
        ], 201);
    }

    /**
     * Helper Function para makuha ang kabuuang salita sa isang kwento
     */
    private function getStoryTotalWords(int $storyId): int
    {
        $scripts = DB::table('story_pages')
            ->where('story_id', $storyId)
            ->orderBy('page_number')
            ->pluck('audio_scripts');

        if ($scripts->isEmpty()) return 0;

        $flatten = function ($v) use (&$flatten) {
            if (is_string($v)) {
                $d = json_decode($v, true);
                return is_array($d) ? $flatten($d) : [$v];
            }
            if (is_array($v)) {
                return collect($v)->flatMap(fn ($x) => $flatten($x))->all();
            }
            return [];
        };

        $words = 0;
        foreach ($scripts as $raw) {
            $seen = [];
            foreach ($flatten($raw) as $line) {
                $clean = trim(preg_replace('/\s+/', ' ', strip_tags((string) $line)));
                $key   = strtolower(preg_replace('/\s+/', '', $clean));
                if ($clean === '' || isset($seen[$key])) continue;
                $seen[$key] = true;
                $words += count(explode(' ', $clean));
            }
        }
        return $words;
    }
}