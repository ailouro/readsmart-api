<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class BackfillWordCounts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'progress:backfill-counts {--dry}';

public function handle(\App\Services\PhilIriService $svc)
{
    $changed = $skipped = 0;

    foreach (\App\Models\StudentProgress::where('is_reading_completed', true)->cursor() as $p) {
        $upd = [];

        // 1. Bilang ng salita (tantiya) para sa lumang records
        if ((int) $p->total_words <= 0) {
            $acc   = $p->word_reading_score_pct ?? $p->oral_fluency_accuracy;
            $words = $svc->storyWordCount((int) $p->story_id);

            if ($acc !== null && (float) $acc > 0 && $words > 0) {
                $miscues = max(0, min($words, (int) round($words * (1 - $acc / 100))));
                $upd['total_words']      = $words;
                $upd['miscues_count']    = $miscues;
                $upd['counts_estimated'] = true;
                if ((int) $p->time_on_task >= 5 && !$p->wpm) {
                    $upd['wpm'] = round(($words - $miscues) / $p->time_on_task * 60, 1);
                }
            } else {
                $skipped++;   // walang magagawa: walang accuracy o walang teksto ang story
            }
        }

        // 2. Comprehension % mula sa quiz na naka-save na
        if ((int) $p->total_questions > 0
            && (int) $p->quiz_score > 0
            && (float) ($p->comprehension_score_pct ?? 0) == 0) {
            $upd['comprehension_score_pct'] = round($p->quiz_score / $p->total_questions * 100, 1);
        }

        if ($upd) {
            $changed++;
            if (!$this->option('dry')) $p->update($upd);
        }
    }

    $this->info("Binago: $changed | Hindi maayos: $skipped" . ($this->option('dry') ? ' (dry run)' : ''));
}
}
