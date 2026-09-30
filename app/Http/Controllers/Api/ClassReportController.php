<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\SchoolClass;
use App\Models\StudentProgress;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Class-level Pre-Test vs Post-Test summary.
 *
 * GET /api/classes/{classId}/pre-post-summary            -> JSON
 * GET /api/classes/{classId}/pre-post-summary?format=csv -> CSV download
 *
 * Only students with BOTH a pre and a post value for a metric count toward
 * that metric's paired statistics (n is reported per metric).
 */
class ClassReportController extends Controller
{
    private const METRICS = ['independent', 'instructional', 'frustration', 'wr', 'comp', 'wpm'];

    public function prePostSummary(Request $request, $classId)
    {
        $class = SchoolClass::with('students')->find($classId);
        if (!$class) {
            return response()->json(['message' => 'Class not found.'], 404);
        }

        $students = $class->students;
        $ids = $students->pluck('id');

        // Phil-IRI level results (official assessment outcome)
        $assessments = Assessment::where('class_id', $classId)
            ->whereIn('student_id', $ids)
            ->where('status', 'completed')
            ->get()
            ->groupBy('student_id');

        // Story-level reading records
        $progress = StudentProgress::whereIn('user_id', $ids)
            ->where('is_reading_completed', true)
            ->whereIn('test_type', ['pre_test', 'post_test'])
            ->get()
            ->groupBy('user_id');

        $rows = $students->map(function ($s) use ($assessments, $progress) {
            $a    = $assessments->get($s->id, collect());
            $pre  = $a->firstWhere('test_type', 'pre_test');
            $post = $a->firstWhere('test_type', 'post_test');

            $logs     = $progress->get($s->id, collect());
            $preLogs  = $logs->where('test_type', 'pre_test');
            $postLogs = $logs->where('test_type', 'post_test');

            $name = $s->name ?: trim(($s->first_name ?? '') . ' ' . ($s->last_name ?? ''));

            $row = [
                'student_id'   => $s->id,
                'name'         => $name ?: 'Student',
                'lrn'          => $s->lrn ?? null,
                'pre_set'      => $pre->set_letter ?? null,
                'post_set'     => $post->set_letter ?? null,
                'pre_level'    => $this->lastLevel($preLogs),
                'post_level'   => $this->lastLevel($postLogs),
                'pre_independent'    => $this->num($pre->independent_grade ?? null),
                'post_independent'   => $this->num($post->independent_grade ?? null),
                'pre_instructional'  => $this->num($pre->instructional_grade ?? null),
                'post_instructional' => $this->num($post->instructional_grade ?? null),
                'pre_frustration'    => $this->num($pre->frustration_grade ?? null),
                'post_frustration'   => $this->num($post->frustration_grade ?? null),
                'pre_wr'    => $this->avg($preLogs->pluck('oral_fluency_accuracy')),
                'post_wr'   => $this->avg($postLogs->pluck('oral_fluency_accuracy')),
                'pre_comp'  => $this->avg($preLogs->map(fn ($l) => $this->compPct($l))),
                'post_comp' => $this->avg($postLogs->map(fn ($l) => $this->compPct($l))),
                'pre_wpm'   => $this->avg($preLogs->pluck('wpm')->filter(fn ($v) => $v > 0)),
                'post_wpm'  => $this->avg($postLogs->pluck('wpm')->filter(fn ($v) => $v > 0)),
            ];

            foreach (self::METRICS as $k) {
                $row["gain_$k"] = ($row["pre_$k"] !== null && $row["post_$k"] !== null)
                    ? round($row["post_$k"] - $row["pre_$k"], 1)
                    : null;
            }
            return $row;
        })->values();

        if ($request->query('format') === 'csv') {
            return $this->csv($class, $rows);
        }

        $metrics = [];
        foreach (self::METRICS as $k) {
            $metrics[$k] = $this->stats($rows, $k);
        }

        // How many students moved up / stayed / dropped in instructional grade
        $movement = ['improved' => 0, 'same' => 0, 'declined' => 0];
        foreach ($rows as $r) {
            $g = $r['gain_instructional'];
            if ($g === null) continue;
            if ($g > 0) $movement['improved']++;
            elseif ($g < 0) $movement['declined']++;
            else $movement['same']++;
        }

        $summary = [
            'total_students'  => $rows->count(),
            'with_pre'        => $rows->filter(fn ($r) => $r['pre_instructional'] !== null || $r['pre_wr'] !== null)->count(),
            'with_post'       => $rows->filter(fn ($r) => $r['post_instructional'] !== null || $r['post_wr'] !== null)->count(),
            'paired_students' => $rows->filter(fn ($r) => $r['gain_instructional'] !== null || $r['gain_wr'] !== null)->count(),
            'movement'        => $movement,
            'levels'          => [
                'pre'  => $this->levelCounts($rows->pluck('pre_level')),
                'post' => $this->levelCounts($rows->pluck('post_level')),
            ],
            'metrics'         => $metrics,
        ];

        if ($request->query('format') === 'pdf') {
            return $this->pdf($class, $rows, $summary);
        }

        return response()->json([
            'success'  => true,
            'class'    => ['id' => $class->id, 'name' => $class->name, 'section' => $class->section],
            'summary'  => $summary,
            'students' => $rows,
        ]);
    }

    private function pdf($class, $rows, $summary)
    {
        $labels = [
            'instructional' => ['Instructional Grade', ''],
            'independent'   => ['Independent Grade', ''],
            'frustration'   => ['Frustration Grade', ''],
            'wr'            => ['Word Reading (WR)', '%'],
            'comp'          => ['Comprehension (Comp)', '%'],
            'wpm'           => ['Words per Minute', ''],
        ];

        $pdf = Pdf::loadView('reports.pre_post_summary', [
            'class'     => $class,
            'rows'      => $rows,
            'summary'   => $summary,
            'labels'    => $labels,
            'generated' => now()->format('F j, Y g:i A'),
        ])->setPaper('a4', 'landscape');

        $file = 'pre-post-' . preg_replace('/[^A-Za-z0-9_-]+/', '-', $class->name) . '.pdf';
        return $pdf->download($file);
    }

    // ---------------------------------------------------------------- helpers

    private function num($v)
    {
        return $v === null ? null : (float) $v;
    }

    private function compPct($log)
    {
        return ($log->total_questions ?? 0) > 0
            ? ($log->quiz_score / $log->total_questions) * 100
            : null;
    }

    private function avg($values)
    {
        $vals = collect($values)->filter(fn ($v) => $v !== null);
        return $vals->isEmpty() ? null : round($vals->avg(), 1);
    }

    private function lastLevel($logs)
    {
        $last = $logs->sortByDesc('updated_at')->first();
        $lvl  = strtolower((string) ($last->reading_level ?? ''));
        foreach (['independent', 'instructional', 'frustration'] as $l) {
            if (str_contains($lvl, $l)) return $l;
        }
        return null;
    }

    private function levelCounts($levels)
    {
        $out = ['independent' => 0, 'instructional' => 0, 'frustration' => 0];
        foreach ($levels as $l) {
            if ($l && isset($out[$l])) $out[$l]++;
        }
        return $out;
    }

    /** Paired statistics for one metric (only students with both pre and post). */
    private function stats($rows, $k)
    {
        $pairs = $rows->filter(fn ($r) => $r["pre_$k"] !== null && $r["post_$k"] !== null)->values();
        $n = $pairs->count();
        if ($n === 0) {
            return ['n' => 0, 'pre_mean' => null, 'post_mean' => null,
                    'mean_gain' => null, 'sd_gain' => null, 't' => null, 'df' => null];
        }

        $gains = $pairs->map(fn ($r) => $r["post_$k"] - $r["pre_$k"]);
        $mean  = $gains->avg();
        $sd    = $n > 1
            ? sqrt($gains->map(fn ($g) => ($g - $mean) ** 2)->sum() / ($n - 1))
            : null;
        $t = ($sd !== null && $sd > 0) ? $mean / ($sd / sqrt($n)) : null;

        return [
            'n'         => $n,
            'pre_mean'  => round($pairs->avg("pre_$k"), 1),
            'post_mean' => round($pairs->avg("post_$k"), 1),
            'mean_gain' => round($mean, 2),
            'sd_gain'   => $sd === null ? null : round($sd, 2),
            't'         => $t === null ? null : round($t, 2),
            'df'        => $n - 1,
        ];
    }

    private function csv($class, $rows)
    {
        $cols = ['name', 'lrn', 'pre_set', 'post_set', 'pre_level', 'post_level'];
        foreach (self::METRICS as $k) {
            array_push($cols, "pre_$k", "post_$k", "gain_$k");
        }

        $fh = fopen('php://temp', 'r+');
        fwrite($fh, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel reads Filipino names correctly
        fputcsv($fh, $cols);
        foreach ($rows as $r) {
            fputcsv($fh, array_map(fn ($c) => $r[$c] ?? '', $cols));
        }
        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);

        $file = 'pre-post-' . preg_replace('/[^A-Za-z0-9_-]+/', '-', $class->name) . '.csv';
        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $file . '"',
        ]);
    }
}