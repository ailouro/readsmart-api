<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveProgressRequest;
use App\Models\StudentProgress;
use App\Models\SelfCorrection;
use App\Services\PhilIriService;
use Illuminate\Http\Request;
use App\Models\SchoolClass;

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


    public function saveProgress(SaveProgressRequest $request)
    {
        $validated = $request->validated();

        $comprehensionPct = ($validated['total_questions'] > 0)
            ? ($validated['quiz_score'] / $validated['total_questions']) * 100
            : 0;

        $readingLevel = $this->philIriService->calculateReadingLevel(
            $validated['oral_fluency_accuracy'], 
            $comprehensionPct
        );

        $wpm = $this->computeWpm(
    $validated['story_id'],
    $validated['oral_fluency_accuracy'],
    $validated['time_on_task']
);  

        $progress = StudentProgress::updateOrCreate(
            [
                'user_id' => $validated['user_id'],
                'story_id' => $validated['story_id'],
                'test_type' => $validated['test_type'], 
            ],
            [
                'quiz_score' => $validated['quiz_score'],
                'total_questions' => $validated['total_questions'],
                'oral_fluency_accuracy' => $validated['oral_fluency_accuracy'],
                'word_reading_score_pct'  => $validated['oral_fluency_accuracy'],
                'time_on_task' => $validated['time_on_task'],
                'wpm' => $wpm, 
                'comprehension_score_pct' => $comprehensionPct,
                'reading_level' => strtolower($readingLevel),
                'is_reading_completed' => true,
            ]
        );

        foreach ($validated['self_corrected_words'] ?? [] as $w) {
            SelfCorrection::create([
                'student_id' => $validated['user_id'],
                'story_id' => $validated['story_id'],
                'word' => strtolower($w['word']),
                'total_attempts' => $w['total_attempts'] ?? 2,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Progress saved successfully',
            'data' => $progress
        ], 201);
    }
    public function checkpoint(Request $r)
{
    $d = $r->validate([
        'user_id'       => 'required|integer',
        'story_id'      => 'required|integer',
        'test_type'     => 'required|string',
        'current_slide' => 'required|integer|min:0',
        'total_slides'  => 'required|integer|min:1',
    ]);

    $p = StudentProgress::firstOrNew([
        'user_id'  => $d['user_id'],
        'story_id' => $d['story_id'],
        'test_type' => $d['test_type'],
    ]);

    if ($p->is_reading_completed) {
        return response()->json(['skipped' => true]);
    }

    $p->test_type     = $d['test_type'];
    $p->current_slide = $d['current_slide'];
    $p->total_slides  = $d['total_slides'];
    $p->status        = 'in_progress';
    $p->save();

    return response()->json(['success' => true]);
}

public function readingProgress($class_id)
{
    $class = SchoolClass::with('students')->find($class_id);
    if (!$class) return response()->json(['data' => []], 404);

    $rows = StudentProgress::whereIn('user_id', $class->students->pluck('id'))
        ->where('is_reading_completed', false)
        ->where('status', 'in_progress')
        ->whereNotNull('total_slides')
        ->with('story:id,title')
        ->orderByDesc('updated_at')
        ->get()
        ->map(function ($p) use ($class) {
            $total = max((int) $p->total_slides, 1);
            $slide = (int) $p->current_slide + 1;
            $stu   = $class->students->firstWhere('id', $p->user_id);
            return [
                'student_id'    => $p->user_id,
                'student_name'  => $stu->name
                    ?? trim(($stu->first_name ?? '') . ' ' . ($stu->last_name ?? ''))
                    ?: 'Student',
                'story_id'      => $p->story_id,
                'story_title'   => $p->story->title ?? 'Story',
                'test_type'     => $p->test_type,
                'current_slide' => $slide,
                'total_slides'  => $total,
                'percent'       => (int) round(min($slide / $total, 1) * 100),
                'updated_at'    => $p->updated_at,
            ];
        })->values();

    return response()->json(['success' => true, 'data' => $rows]);
}

private function computeWpm(int $storyId, $accuracy, $seconds): ?float
{
    if (!$seconds || $seconds <= 0) return null;

    $scripts = \DB::table('story_pages')
        ->where('story_id', $storyId)
        ->orderBy('page_number')
        ->pluck('audio_scripts');

    if ($scripts->isEmpty()) return null;

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
            if ($clean === '' || isset($seen[$key])) continue;   // skip kung kapareho na ng nauna
            $seen[$key] = true;
            $words += count(explode(' ', $clean));
        }
    }
    if ($words === 0) return null;

    $correct = $words * (max(0, min(100, (float) $accuracy)) / 100);

    return round($correct / ($seconds / 60), 1);
}
}