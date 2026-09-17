<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Student;
use App\Models\StudentProgress;
use Illuminate\Support\Facades\DB;

class AlertController extends Controller
{
    public function getFrustrationAlerts($teacherId)
    {
        try {
            $alerts = [];

            // Use the SAME relationship style as AnalyticsController's dashboardSummary,
            // instead of rebuilding the teacher -> class -> student chain by hand with
            // raw DB::table joins (that manual chain is the likely source of the bug --
            // if the pivot column names don't match exactly, it silently returns nothing).
            $students = Student::whereHas('classes', function ($query) use ($teacherId) {
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
}