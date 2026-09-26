<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\StudentProgress; 
use App\Models\Mispronunciation; 
use App\Models\User;

class AnalyticsController extends Controller
{
    /**
     * Endpoint 1: Dashboard Summary
     */
    public function dashboardSummary($teacherId)
    {
        try {
            // Every reading attempt for students in any of this teacher's
            // classes that already has a Phil-IRI reading_level computed.
            // (Interim/in-progress rows have reading_level = null and are
            // correctly excluded, same as before.)
            $progress = StudentProgress::with(['student.classes' => function ($query) use ($teacherId) {
                    $query->where('teacher_id', $teacherId);
                }])
                ->whereHas('student.classes', function ($query) use ($teacherId) {
                    $query->where('teacher_id', $teacherId);
                })
                ->whereNotNull('reading_level')
                ->get();

            $frustration = 0;
            $instructional = 0;
            $independent = 0;

            // Per class + section breakdown (e.g. "Grade 5 - Magsaysay"), so
            // the dashboard chart can show exactly where each count comes
            // from, and hovering/tapping a bar can name the class/section.
            $classBreakdown = [];

            foreach ($progress as $row) {
                if (in_array($row->reading_level, ['frustration', 'instructional', 'independent'], true)) {
                    switch ($row->reading_level) {
                        case 'frustration':
                            $frustration++;
                            break;
                        case 'instructional':
                            $instructional++;
                            break;
                        case 'independent':
                            $independent++;
                            break;
                    }
                }

                if (!$row->student) {
                    continue;
                }

                foreach ($row->student->classes as $class) {
                    $key = $class->id;
                    if (!isset($classBreakdown[$key])) {
                        $classBreakdown[$key] = [
                            'class_id' => $class->id,
                            'grade_level' => $class->grade_level,
                            'section' => $class->section,
                            'label' => trim('Grade ' . $class->grade_level . ' - ' . $class->section, ' -'),
                            'frustration' => 0,
                            'instructional' => 0,
                            'independent' => 0,
                            'total' => 0,
                        ];
                    }
                    if (in_array($row->reading_level, ['frustration', 'instructional', 'independent'], true)) {
                        $classBreakdown[$key][$row->reading_level]++;
                        $classBreakdown[$key]['total']++;
                    }
                }
            }

            // Roster for the "Learner's Individual Record Card" / "Learners'
            // Records" sections further down both dashboards. This endpoint
            // never returned a 'students' key before, which is why those
            // sections always showed "No students enrolled yet." even when
            // the teacher had classes full of students.
            $students = User::whereHas('classes', function ($query) use ($teacherId) {
                    $query->where('teacher_id', $teacherId);
                })
                ->with(['classes' => function ($query) use ($teacherId) {
                    $query->where('teacher_id', $teacherId);
                }])
                ->get()
                ->map(function ($student) {
                    $class = $student->classes->first();
                    return [
                        'id' => $student->id,
                        'name' => $student->name,
                        'lrn' => $student->lrn ?? null,
                        'grade_level' => $student->grade_level ?? optional($class)->grade_level,
                        'section' => $student->section ?? optional($class)->section,
                    ];
                })
                ->values();

            return response()->json([
                'frustration_count' => $frustration,
                'instructional_count' => $instructional,
                'independent_count' => $independent,
                'class_breakdown' => array_values($classBreakdown),
                'students' => $students,
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