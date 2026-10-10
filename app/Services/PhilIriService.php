<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;

class PhilIriService
{
    private const RANK = ['Frustration' => 0, 'Instructional' => 1, 'Independent' => 2];

    public function wordReadingLevel(float $oralAccuracy): string
    {
        if ($oralAccuracy >= 97) return 'Independent';
        if ($oralAccuracy >= 90) return 'Instructional';
        return 'Frustration';
    }

    public function comprehensionLevel(float $comprehensionPct): string
    {
        $pct = round($comprehensionPct);
        if ($pct >= 80) return 'Independent';
        if ($pct >= 59) return 'Instructional';
        return 'Frustration';
    }

    public function calculateReadingLevel(float $oralAccuracy, float $comprehensionPct): string
    {
        $wr = $this->wordReadingLevel($oralAccuracy);
        $c  = $this->comprehensionLevel($comprehensionPct);

        return self::RANK[$wr] <= self::RANK[$c] ? $wr : $c;
    }

    /**
     * Helper para i-compute ang kumpletong Phil-IRI details kada kwento
     */
    public function getDetailedBreakdown(int $totalWords, int $miscues, int $quizScore, int $totalQuestions, int $seconds): array
    {
        $correctWords = max(0, $totalWords - $miscues);
        $oralAccuracy = $totalWords > 0 ? round(($correctWords / $totalWords) * 100, 1) : 0;
        $compPct      = $totalQuestions > 0 ? round(($quizScore / $totalQuestions) * 100, 1) : 0;
        $wpm          = $seconds > 0 ? round(($correctWords / $seconds) * 60, 1) : 0;

        $wrLevel    = $this->wordReadingLevel($oralAccuracy);
        $compLevel  = $this->comprehensionLevel($compPct);
        $finalLevel = $this->calculateReadingLevel($oralAccuracy, $compPct);

        return [
            'total_words'             => $totalWords,
            'miscues_count'           => $miscues,
            'correct_words'           => $correctWords,
            'word_reading_score_pct'  => $oralAccuracy,
            'quiz_score'              => $quizScore,
            'total_questions'         => $totalQuestions,
            'comprehension_score_pct' => $compPct,
            'time_on_task_seconds'    => $seconds,
            'wpm'                     => $wpm,
            'word_reading_level'      => $wrLevel,
            'comprehension_level'     => $compLevel,
            'final_reading_level'     => strtolower($finalLevel),
        ];
    }

    public function storyWordCount(int $storyId): int
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