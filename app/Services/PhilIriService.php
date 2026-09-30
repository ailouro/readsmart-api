<?php

namespace App\Services;

class PhilIriService
{
    public function calculateReadingLevel(float $oralAccuracy, float $comprehensionPct): string
    {
        // 1. Word Recognition (WR)
        $wrLevel = 'Frustration';
        if ($oralAccuracy >= 97) {
            $wrLevel = 'Independent';
        } elseif ($oralAccuracy >= 90) {
            $wrLevel = 'Instructional';
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
}