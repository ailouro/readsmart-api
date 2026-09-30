<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\StudentProgress;
use Illuminate\Support\Facades\DB;

class AlertController extends Controller
{
    public function getFrustrationAlerts($teacherId)
    {
        try {
            $alerts = [];

            // 🛠️ FIX: same disconnected-table bug that ClassController hit —
            // App\Models\Student points at the `students` table, which only
            // ever had 1 row and 0 rows with teacher_id set. The real
            // student roster lives on `users` (role = student), joined to
            // classes through the class_student pivot, so query that
            // instead — same pattern as ClassController::getAvailableStudents.
            $students = User::where('role', 'student')
                ->whereHas('classes', function ($query) use ($teacherId) {
                    $query->where('teacher_id', $teacherId);
                })
                ->with(['classes' => function ($query) use ($teacherId) {
                    $query->where('teacher_id', $teacherId);
                }])
                ->get();

            foreach ($students as $student) {
                // 🛠️ FIX: StudentProgress's foreign key is `user_id`, not
                // `student_id` — every other place in the app that queries
                // this table (ClassEnrollmentController::saveProgress,
                // getTeacherDashboardSummary) uses `user_id`. Querying
                // `student_id` here matched zero rows every time, so
                // $latestProgress->count() was always 0, `continue` always
                // fired, and no alert could ever be generated — this was
                // almost certainly the actual bug behind the empty results.
                $latestProgress = StudentProgress::where('user_id', $student->id)
                    ->orderBy('created_at', 'desc')
                    ->take(2)
                    ->get();

                // Need at least 2 recorded tests before we can flag "consistently frustrated"
                if ($latestProgress->count() < 2) {
                    continue;
                }

                $isConsistentlyFrustrated = $latestProgress->every(function ($progress) {
                    return strtolower(trim($progress->reading_level)) === 'frustration';
                });

                if ($isConsistentlyFrustrated) {
                    $studentClass = $student->classes->first();

                    $alerts[] = [
                        'student_id' => $student->id,
                        'student_name' => $student->name ?? $student->username,
                        'class_name' => $studentClass->name ?? 'No Class',
                        'reason' => 'Consistently at Frustration Level (Last 2 tests)',
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => $alerts,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch frustration alerts',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

   public function getStudentAlertDetail($teacherId, $studentId)
{
    try {
        $student = User::where('role', 'student')
            ->where('id', $studentId)
            ->whereHas('classes', fn ($q) => $q->where('teacher_id', $teacherId))
            ->with(['classes' => fn ($q) => $q->where('teacher_id', $teacherId)])
            ->firstOrFail();

        $rows = StudentProgress::with('story')
            ->where('user_id', $student->id)
            ->orderByDesc('created_at')
            ->take(10)
            ->get();

        // Pagsamahin ang struggled words sa lahat ng sessions, bilangin kung ilang beses lumabas
        $wordCounts = [];
        foreach ($rows as $r) {
            $words = $r->struggled_words;
            if (is_string($words)) {
                $decoded = json_decode($words, true);
                $words = is_array($decoded)
                    ? $decoded
                    : array_map('trim', explode(',', $words));
            }
            foreach ((array) $words as $w) {
                $w = strtolower(trim(is_array($w) ? ($w['word'] ?? '') : (string) $w));
                if ($w === '') continue;
                $wordCounts[$w] = ($wordCounts[$w] ?? 0) + 1;
            }
        }
        arsort($wordCounts);
        $topWords = [];
        foreach (array_slice($wordCounts, 0, 15, true) as $word => $count) {
            $topWords[] = ['word' => $word, 'count' => $count];
        }

        $latest = $rows->first();

        return response()->json([
            'success' => true,
            'data' => [
                'student_id'   => $student->id,
                'student_name' => $student->name ?? $student->username,
                'class_name'   => $student->classes->first()->name ?? 'No Class',
                'latest' => $latest ? [
                    'reading_level'           => $latest->reading_level,
                    'reading_profile'         => $latest->reading_profile,
                    'wpm'                     => $latest->wpm,
                    'oral_fluency_accuracy'   => $latest->oral_fluency_accuracy,
                    'comprehension_score_pct' => $latest->comprehension_score_pct,
                    'word_reading_score_pct'  => $latest->word_reading_score_pct,
                ] : null,
                'top_struggled_words' => $topWords,
                'history' => $rows->map(fn ($r) => [
                    'reading_level'   => $r->reading_level,
                    'test_type'       => $r->test_type,
                    'story_title'     => $r->story->title ?? null,
                    'quiz_score'      => $r->quiz_score,
                    'total_questions' => $r->total_questions,
                    'wpm'             => $r->wpm,
                    'time_on_task'    => $r->time_on_task,
                    'date'            => optional($r->created_at)->toDateString(),
                ])->values(),
            ],
        ], 200);
    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        return response()->json(['success' => false, 'error' => 'Student not found'], 404);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
    }
}
}