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

    /**
     * Phil-IRI reading speed:
     *   WPM = (No. of words read / Reading time in seconds) x 60
     * Halimbawa: 103 salita / 90 segundo x 60 = 69 WPM.
     * Ang "words read" ay ang kabuuang salita ng passage na binasa.
     * Ibinabalik ang 0 kapag kulang ang datos (walang salita, o mas mababa sa
     * 5 segundo) o imposibleng bilis (> 250), para hindi mag-imbento ng numero.
     */
    public function wordsPerMinute(int $words, int $seconds): float
    {
        if ($words <= 0 || $seconds < 5) return 0;
        $wpm = ($words / $seconds) * 60;
        return $wpm > 250 ? 0 : round($wpm, 1);
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
    public function getDetailedBreakdown(
    int $totalWords, int $miscues, int $quizScore,
    int $totalQuestions, int $seconds, ?int $quizAnswered = null
): array {
    $correctWords = max(0, $totalWords - $miscues);

    $hasWR   = $totalWords > 0 && $correctWords > 0;
    $hasComp = $totalQuestions > 0
        && ($quizAnswered !== null ? $quizAnswered > 0 : $quizScore > 0);

    $oralAccuracy = $hasWR   ? round(($correctWords / $totalWords) * 100, 1) : null;
    $compPct      = $hasComp ? round(($quizScore / $totalQuestions) * 100, 1) : null;
    $wpm          = $hasWR ? $this->wordsPerMinute($totalWords, $seconds) : 0;

    $wrLevel   = $hasWR   ? $this->wordReadingLevel($oralAccuracy) : 'not_started';
    $compLevel = $hasComp ? $this->comprehensionLevel($compPct)    : 'not_started';

    if ($hasWR && $hasComp) {
        $final = strtolower($this->calculateReadingLevel($oralAccuracy, $compPct));
    } elseif (!$hasWR && !$hasComp) {
        $final = 'not_started';
    } else {
        $final = 'incomplete';
    }

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
        'final_reading_level'     => $final,
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