<?php

namespace App\Services;

class PhilIriService
{
    /**
     * Overall Phil-IRI level of one reading = the LOWER of Word Recognition
     * and Comprehension.
     *
     * Pass null for $comprehensionPct when the story has no quiz: the level
     * is then decided by Word Recognition alone (same as the app's
     * "word reading only" rule in the assessment flow), instead of treating
     * the missing quiz as 0% and forcing Frustration.
     */
    public function calculateReadingLevel(float $oralAccuracy, ?float $comprehensionPct): string
    {
        // 1. Word Recognition (WR)
        $wrLevel = 'Frustration';
        if ($oralAccuracy >= 97) {
            $wrLevel = 'Independent';
        } elseif ($oralAccuracy >= 90) {
            $wrLevel = 'Instructional';
        }

        // No quiz -> Word Recognition alone decides.
        if ($comprehensionPct === null) {
            return $wrLevel;
        }

        // 2. Comprehension (C)
        $cLevel = 'Frustration';
        if ($comprehensionPct >= 80) {
            $cLevel = 'Independent';
        } elseif ($comprehensionPct >= 59) {
            $cLevel = 'Instructional';
        }

        // 3. Overall Reading Level
        if ($wrLevel === 'Independent') {
            if ($cLevel === 'Independent') return 'Independent';
            if ($cLevel === 'Instructional') return 'Instructional';
            return 'Frustration';
        }

        if ($wrLevel === 'Instructional') {
            if ($cLevel === 'Independent' || $cLevel === 'Instructional') return 'Instructional';
            return 'Frustration';
        }

        return 'Frustration';
    }

    /**
     * Convenience wrapper for raw quiz numbers. total_questions = 0 means the
     * story has no quiz.
     */
    public function levelFromScores(float $oralAccuracy, $quizScore, $totalQuestions): string
    {
        $total = (float) $totalQuestions;
        $pct = $total > 0 ? ((float) $quizScore / $total) * 100 : null;

        return $this->calculateReadingLevel($oralAccuracy, $pct);
    }
}