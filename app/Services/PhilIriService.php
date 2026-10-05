<?php

namespace App\Services;

class PhilIriService
{
    public function calculateReadingLevel(float $oralAccuracy, float $comprehensionPct): string
    {
        $comprehensionPct = round($comprehensionPct); // Table 6

        $rank = ['Frustration' => 0, 'Instructional' => 1, 'Independent' => 2];

        $wr = $oralAccuracy >= 97 ? 'Independent' : ($oralAccuracy >= 90 ? 'Instructional' : 'Frustration');
        $c  = $comprehensionPct >= 80 ? 'Independent' : ($comprehensionPct >= 59 ? 'Instructional' : 'Frustration');

        return $rank[$wr] <= $rank[$c] ? $wr : $c;
    }
}