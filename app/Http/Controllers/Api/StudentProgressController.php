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

        $key = [
            'user_id'   => $validated['user_id'],
            'story_id'  => $validated['story_id'],
            'test_type' => $validated['test_type'],
        ];
        $existing = StudentProgress::where($key)->first();

        // May dalang datos ng pagbasa ba ang request na ito? (Ang POST mula sa
        // reading screen ay meron; ang POST mula sa quiz ay baka wala.)
        $hasReadingData = $request->filled('total_words')
            || $request->filled('miscues_count')
            || $request->filled('correct_words');

        $accuracy = (float) $validated['oral_fluency_accuracy'];
        $seconds  = (int) $validated['time_on_task'];

        if (!$hasReadingData && $existing && (int) $existing->total_words > 0) {
            // Quiz POST na walang datos ng pagbasa: HUWAG palitan ang totoong
            // total_words/miscues/oras na nai-save na ng reading screen.
            $totalWords = (int) $existing->total_words;
            $miscues    = (int) $existing->miscues_count;
            if ((int) $existing->time_on_task > 0) {
                $seconds = (int) $existing->time_on_task;
            }
        } else {
            // 1. Ang bilang ng salita na MISMONG nabasa/na-grade ng app ang
            //    pinagkakatiwalaan. Ang bilang mula sa audio_scripts ay fallback
            //    lang (iba ang paraan ng bilang kaya hindi tugma sa app).
            $clientWords = (int) $request->input('total_words', 0);
            $totalWords  = $clientWords > 0
                ? $clientWords
                : $this->getStoryTotalWords($validated['story_id']);

            // 2. Totoong miscues galing sa app; kung wala, mula sa correct_words;
            //    huling opsyon lang ang hula mula sa accuracy %.
            if ($request->filled('miscues_count')) {
                $miscues = (int) $request->input('miscues_count');
            } elseif ($request->filled('correct_words') && $totalWords > 0) {
                $miscues = $totalWords - (int) $request->input('correct_words');
            } else {
                $miscues = (int) round($totalWords * (1 - ($accuracy / 100)));
            }
            $miscues = max(0, min($miscues, $totalWords));
        }

        // 3. Compute detailed Phil-IRI metrics
        $breakdown = $this->philIriService->getDetailedBreakdown(
            $totalWords,
            $miscues,
            (int) $validated['quiz_score'],
            (int) $validated['total_questions'],
            $seconds
        );

        // Masyadong maikli ang oras para maging makabuluhan ang WPM.
        if ($breakdown['time_on_task_seconds'] < 5) {
            $breakdown['wpm'] = 0;
        }

        // 4. I-save sa database
        $values = [
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
            'is_reading_completed'    => true,
        ];

        // Mic health: i-update lang kapag may dala ang request (para hindi
        // mabura ng quiz POST ang datos ng reading POST).
        foreach (['mic_status', 'mic_peak_level', 'asr_drops'] as $f) {
            if ($request->filled($f)) {
                $values[$f] = $request->input($f);
            }
        }

        $progress = StudentProgress::updateOrCreate($key, $values);

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