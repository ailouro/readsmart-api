<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveProgressRequest;
use App\Models\StudentProgress;
use App\Models\SelfCorrection;
use App\Services\PhilIriService;

class StudentProgressController extends Controller
{
    protected PhilIriService $philIriService;

    public function __construct(PhilIriService $philIriService)
    {
        $this->philIriService = $philIriService;
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

        $progress = StudentProgress::updateOrCreate(
            [
                'user_id' => $validated['user_id'],
                'story_id' => $validated['story_id'],
            ],
            [
                'test_type' => $validated['test_type'] ?? 'post_test',
                'quiz_score' => $validated['quiz_score'],
                'total_questions' => $validated['total_questions'],
                'oral_fluency_accuracy' => $validated['oral_fluency_accuracy'],
                'time_on_task' => $validated['time_on_task'],
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
}