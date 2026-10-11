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
     *
     * BAGO: ang pooled Word Reading ay mula lang sa mga story na MAY totoong
     * word reading record, at ang pooled Comprehension ay mula lang sa mga
     * story na may quiz. Kapag kulang ang isa, ang level ay 'incomplete'
     * (hindi na nagiging Frustration dahil lang walang word reading).
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

                // Kung walang comprehension % pero may quiz, kuwentahin
                $compPct = $p->comprehension_score_pct;
                if (($compPct === null || (float) $compPct == 0)
                    && (int) $p->total_questions > 0
                    && (int) $p->quiz_score > 0) {
                    $compPct = round($p->quiz_score / $p->total_questions * 100, 1);
                }

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
                    'comprehension_score_pct' => $compPct,
                    'counts_estimated'        => (bool) $p->counts_estimated,
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

                // Word Reading: tanging mga story na may totoong word reading %.
                $wrSet   = $set->filter(fn ($r) => $r['word_reading_score_pct'] !== null);
                $words   = (int) $wrSet->sum('total_words');
                $miscues = (int) $wrSet->sum('miscues_count');
                $correct = max(0, $words - $miscues);

                // Comprehension: tanging mga story na may comprehension %.
                $compSet = $set->filter(fn ($r) => $r['comprehension_score_pct'] !== null);
                $quizC   = (int) $compSet->sum('quiz_score');
                $quizQ   = (int) $compSet->sum('total_questions');

                // Kung may lumang records na walang total_words, average ang fallback.
                if ($words > 0) {
                    $wordPct = round(($correct / $words) * 100, 1);
                } elseif ($wrSet->isNotEmpty()) {
                    $wordPct = round((float) $wrSet->avg('word_reading_score_pct'), 1);
                } else {
                    $wordPct = null;
                }

                if ($quizQ > 0) {
                    $compPct = (int) round(($quizC / $quizQ) * 100);
                } elseif ($compSet->isNotEmpty()) {
                    $compPct = (int) round((float) $compSet->avg('comprehension_score_pct'));
                } else {
                    $compPct = null;
                }

                $level = ($wordPct !== null && $compPct !== null)
                    ? strtolower($this->philIriService->calculateReadingLevel($wordPct, $compPct))
                    : 'incomplete';

                $totals[$type] = [
                    'stories'        => $set->count(),
                    'total_words'    => $words,
                    'miscues'        => $miscues,
                    'correct_words'  => $correct,
                    'word_pct'       => $wordPct,
                    'quiz_correct'   => $quizC,
                    'quiz_questions' => $quizQ,
                    'comp_pct'       => $compPct,
                    'level'          => $level,
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
        //
        // BAGO: ang `filled('total_words')` ay TRUE pa rin kahit 0 ang ipinadala
        // (hal. quiz POST na may totalWords: 0). Kaya tinitingnan na ngayon
        // kung talagang > 0 ang halaga.
        $hasReadingData = (int) $request->input('total_words', 0) > 0
            || $request->filled('miscues_count')
            || (int) $request->input('correct_words', 0) > 0;

        $accuracy = (float) $validated['oral_fluency_accuracy'];
        $seconds  = (int) $validated['time_on_task'];

        // QUIZ LANG, WALANG PAGBASA: huwag mag-imbento ng miscues/word reading.
        // I-save lang ang quiz; walang word reading = 'incomplete' ang level.
        $quizOnly = !$hasReadingData
            && $accuracy <= 0
            && (!$existing || (int) $existing->total_words <= 0);

        if ($quizOnly) {
            $qs = (int) $validated['quiz_score'];
            $tq = (int) $validated['total_questions'];

            $progress = StudentProgress::updateOrCreate($key, [
                'quiz_score'              => $qs,
                'total_questions'         => $tq,
                'comprehension_score_pct' => $tq > 0 ? round($qs / $tq * 100, 1) : null,
                'reading_level'           => 'incomplete',
                'is_reading_completed'    => true, // para makita pa rin sa "Kulang ang record"
            ]);

            return response()->json([
                'success'   => true,
                'message'   => 'Quiz saved; word reading is still missing.',
                'data'      => $progress,
                'breakdown' => [
                    'word_reading_score_pct'  => null,
                    'comprehension_score_pct' => $progress->comprehension_score_pct,
                    'word_reading_level'      => 'not_started',
                    'final_reading_level'     => 'incomplete',
                ],
            ], 201);
        }

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
                : $this->philIriService->storyWordCount((int) $validated['story_id']);

            // 2. Totoong miscues galing sa app; kung wala, mula sa correct_words;
            //    kung quiz POST na pareho ang total_words sa naka-save na,
            //    panatilihin ang eksaktong miscues; huling opsyon lang ang hula
            //    mula sa accuracy %.
            if ($request->filled('miscues_count')) {
                $miscues = (int) $request->input('miscues_count');
            } elseif ($request->filled('correct_words') && $totalWords > 0) {
                $miscues = $totalWords - (int) $request->input('correct_words');
            } elseif ($existing
                && (int) $existing->total_words > 0
                && (int) $existing->total_words === $totalWords
                && $existing->miscues_count !== null) {
                $miscues = (int) $existing->miscues_count;
            } else {
                $miscues = (int) round($totalWords * (1 - ($accuracy / 100)));
            }
            $miscues = max(0, min($miscues, $totalWords));
        }

        // 3. Compute detailed Phil-IRI metrics
        //    Ipinapasa ang total_questions bilang "quizAnswered": ang quiz POST
        //    ay ipinapadala lang kapag natapos ang quiz, kaya ang batang may
        //    0 na score ay may comprehension record pa rin (0%), hindi
        //    'not_started'. Ang reading POST ay total_questions = 0 kaya
        //    hindi pa ito binibilang na may comprehension.
        $breakdown = $this->philIriService->getDetailedBreakdown(
            $totalWords,
            $miscues,
            (int) $validated['quiz_score'],
            (int) $validated['total_questions'],
            $seconds,
            (int) $validated['total_questions']
        );

        // Masyadong maikli ang oras para maging makabuluhan ang WPM.
        if ($breakdown['wpm'] > 250) {
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
}