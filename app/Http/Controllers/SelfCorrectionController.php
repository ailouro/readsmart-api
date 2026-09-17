<?php

namespace App\Http\Controllers;

use App\Models\SelfCorrection;
use Illuminate\Http\Request;

class SelfCorrectionController extends Controller
{
    // ---------------------------------------------------------------------
    // POST /api/student-self-corrections
    // Called from story_view_screen.dart's _notifyTeacherOfSelfCorrectedWords.
    // Mirrors whatever your existing student-mispronunciations method does,
    // minus the audio file handling (self-corrections have no failure audio).
    // ---------------------------------------------------------------------
    public function storeSelfCorrections(Request $request)
    {
        $studentId = $request->input('student_id');
        $storyId = $request->input('story_id');
        $wordsJson = $request->input('words_json');

        $words = json_decode($wordsJson, true) ?? [];

        foreach ($words as $w) {
            SelfCorrection::create([
                'student_id' => $studentId,
                'story_id' => $storyId,
                'word' => $w['word'],
                'total_attempts' => $w['total_attempts'] ?? 2,
            ]);
        }

        return response()->json(['status' => 'ok', 'count' => count($words)]);
    }

    // ---------------------------------------------------------------------
    // GET /api/teachers/{id}/self-corrections
    // Mirrors the existing mispronunciations-by-teacher endpoint: return
    // every self-correction logged by any student belonging to this teacher.
    //
    // 🛠️ FIX: was guessing at a `teacher_id` column directly on the student.
    // The real relation (confirmed against ClassEnrollmentController::
    // getTeacherStudentLogs, which already works) is student -> classes
    // (many-to-many) -> class.teacher_id. whereHas('student', ...) was
    // silently matching nothing since 'teacher_id' isn't a column on
    // students at all.
    // ---------------------------------------------------------------------
    public function teacherSelfCorrections($teacherId)
    {
        $selfCorrections = SelfCorrection::whereHas('student.classes', function ($q) use ($teacherId) {
                $q->where('teacher_id', $teacherId);
            })
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['data' => $selfCorrections]);
    }
}