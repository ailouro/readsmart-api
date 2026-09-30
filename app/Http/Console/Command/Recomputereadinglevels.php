<?php

namespace App\Console\Commands;

use App\Models\StudentProgress;
use App\Services\PhilIriService;
use Illuminate\Console\Command;

class RecomputeReadingLevels extends Command
{
    protected $signature = 'philiri:recompute
                            {--apply : Save the changes. Without this flag it is a dry run.}
                            {--student= : Only recompute this user_id}';

    protected $description = 'Recompute reading_level of completed readings using PhilIriService';

    public function handle(PhilIriService $service): int
    {
        $apply = (bool) $this->option('apply');

        $query = StudentProgress::query()
            ->where('is_reading_completed', true)
            ->whereNotNull('oral_fluency_accuracy');

        if ($this->option('student')) {
            $query->where('user_id', (int) $this->option('student'));
        }

        $total = 0;
        $changed = 0;
        $transitions = [];
        $samples = [];

        $query->chunkById(200, function ($rows) use (
            $service, $apply, &$total, &$changed, &$transitions, &$samples
        ) {
            foreach ($rows as $row) {
                $total++;

                $old = strtolower(trim((string) $row->reading_level));
                $new = $service->levelFromScores(
                    (float) $row->oral_fluency_accuracy,
                    $row->quiz_score ?? 0,
                    $row->total_questions ?? 0
                );

                // Only a real level change counts (ignores Capitalization).
                if ($old === strtolower($new)) {
                    continue;
                }

                $changed++;
                $key = ($old === '' ? '(blank)' : ucfirst($old)) . ' -> ' . $new;
                $transitions[$key] = ($transitions[$key] ?? 0) + 1;

                if (count($samples) < 15) {
                    $samples[] = [
                        $row->id,
                        $row->user_id,
                        $row->story_id,
                        $key,
                        $row->oral_fluency_accuracy . '%',
                        ((float) ($row->total_questions ?? 0)) > 0
                            ? ($row->quiz_score . '/' . $row->total_questions)
                            : 'no quiz',
                    ];
                }

                if ($apply) {
                    // Keep updated_at as-is: the dashboards use it to decide
                    // which reading is each student's "latest".
                    $row->timestamps = false;
                    $row->reading_level = $new;
                    $row->save();
                }
            }
        });

        $this->info("Checked {$total} completed reading(s). {$changed} would change.");

        if ($transitions) {
            ksort($transitions);
            $this->table(
                ['Change', 'Rows'],
                collect($transitions)->map(fn ($n, $k) => [$k, $n])->values()->all()
            );
            $this->line('Sample:');
            $this->table(
                ['progress id', 'user', 'story', 'change', 'word reading', 'quiz'],
                $samples
            );
        }

        if ($apply) {
            $this->info("Applied. {$changed} row(s) updated.");
        } else {
            $this->warn('Dry run only - nothing was saved. Re-run with --apply to save.');
        }

        return self::SUCCESS;
    }
}