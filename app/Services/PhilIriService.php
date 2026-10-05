<?php

namespace App\Services;

class PhilIriService
{
    private const RANK = ['Frustration' => 0, 'Instructional' => 1, 'Independent' => 2];

    /**
     * Word Reading level (Phil-IRI Table 7): 97-100 Independent,
     * 90-96 Instructional, 89 and below Frustration.
     */
    public function wordReadingLevel(float $oralAccuracy): string
    {
        if ($oralAccuracy >= 97) return 'Independent';
        if ($oralAccuracy >= 90) return 'Instructional';
        return 'Frustration';
    }

    /**
     * Comprehension level (Phil-IRI Table 7): 80-100 Independent,
     * 59-79 Instructional, 58 and below Frustration.
     * The percentage is rounded to a whole number first (Table 6).
     */
    public function comprehensionLevel(float $comprehensionPct): string
    {
        $pct = round($comprehensionPct);
        if ($pct >= 80) return 'Independent';
        if ($pct >= 59) return 'Instructional';
        return 'Frustration';
    }

    /**
     * Final level = the LOWER of word reading and comprehension.
     * (Project decision: the manual reports the two separately.)
     */
    public function calculateReadingLevel(float $oralAccuracy, float $comprehensionPct): string
    {
        $wr = $this->wordReadingLevel($oralAccuracy);
        $c  = $this->comprehensionLevel($comprehensionPct);

        return self::RANK[$wr] <= self::RANK[$c] ? $wr : $c;
    }
}