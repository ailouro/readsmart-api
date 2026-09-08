<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\StudentProgress; 
use App\Models\Mispronunciation; 

class AnalyticsController extends Controller
{
    /**
     * Endpoint 1: Dashboard Summary
     */
    public function dashboardSummary($teacherId)
    {
        try {
            // Uses the actual 'reading_level' column from your migration
            $frustration = StudentProgress::whereHas('student.classes', function ($query) use ($teacherId) {
                    $query->where('teacher_id', $teacherId);
                })
                ->where('reading_level', 'frustration')
                ->count();

            $instructional = StudentProgress::whereHas('student.classes', function ($query) use ($teacherId) {
                    $query->where('teacher_id', $teacherId);
                })
                ->where('reading_level', 'instructional')
                ->count();

            $independent = StudentProgress::whereHas('student.classes', function ($query) use ($teacherId) {
                    $query->where('teacher_id', $teacherId);
                })
                ->where('reading_level', 'independent')
                ->count();

            return response()->json([
                'frustration_count' => $frustration,
                'instructional_count' => $instructional,
                'independent_count' => $independent,
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch dashboard summary', 'details' => $e->getMessage()], 500);
        }
    }

    /**
     * Endpoint 2: Mispronunciations
     */
    public function mispronunciations($teacherId)
    {
        try {
            // Because you log every mistake individually, we use DB::raw to group them 
            // by word, student, and story, and count how many times it occurred.
            $logs = Mispronunciation::with(['student'])
                ->whereHas('student.classes', function ($query) use ($teacherId) {
                    $query->where('teacher_id', $teacherId);
                })
                ->select(
                    'word',
                    'student_id',
                    'story_id',
                    'story_title',
                    DB::raw('count(*) as total_attempts')
                )
                ->groupBy('word', 'student_id', 'story_id', 'story_title')
                ->orderByDesc('total_attempts')
                ->take(20)
                ->get();

            $formattedData = $logs->map(function ($log) {
                return [
                    'word' => $log->word,
                    'student_id' => $log->student_id,
                    'story_id' => $log->story_id,
                    'total_attempts' => $log->total_attempts, // From the DB::raw count above
                    'student' => [
                        'id' => $log->student_id,
                        'name' => $log->student ? ($log->student->name ?? $log->student->username ?? 'Unknown') : 'Unknown',
                    ],
                    'story' => [
                        'id' => $log->story_id,
                        'title' => $log->story_title, // Directly uses your story_title string column
                    ]
                ];
            });

            return response()->json([
                'data' => $formattedData
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch mispronunciation data', 'details' => $e->getMessage()], 500);
        }
    }
}