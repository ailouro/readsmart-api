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

    public function getCompletedStories($studentId)
    {
        try {
            // Gamitin ang tamang column names: user_id at is_reading_completed
            $stories = Story::join('student_progress', 'stories.id', '=', 'student_progress.story_id')
                ->where('student_progress.user_id', $studentId)
                ->where('student_progress.is_reading_completed', true)
                ->select(
                    'stories.*', 
                    'student_progress.quiz_score', 
                    'student_progress.reading_level', 
                    'student_progress.updated_at as date_completed'
                )
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Completed stories retrieved successfully.',
                'data' => $stories
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch completed stories.',
                'error' => $e->getMessage()
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