<?php
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AlertController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\StoryController;
use App\Http\Controllers\StoryAudioController;
use App\Http\Controllers\StudentClassController;
use App\Http\Controllers\TeacherEnrollmentController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ParentController;
use App\Http\Controllers\Api\ClassEnrollmentController;
use App\Http\Controllers\Api\ClassController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\SelfCorrectionController;


Route::post('/classes', [ClassController::class, 'store']);
Route::get('/classes/{id}/stories', [ClassEnrollmentController::class, 'getClassStories']);
Route::post('/classes/{id}/assign-story', [ClassEnrollmentController::class, 'assignStoryToClass']);
Route::post('/classes/{id}/unassign-story', [ClassEnrollmentController::class, 'unassignStoryFromClass']);
Route::post('/classes', [ClassEnrollmentController::class, 'createClass']);
Route::get('/get-audio', [StoryAudioController::class, 'getAudio']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/admin/students', [AdminController::class, 'createStudent']);
Route::get('/admin/approvals', [AdminController::class, 'pendingApprovals']);
Route::post('/admin/approvals/{id}/approve', [AdminController::class, 'approveAccount']);
Route::get('/admin/student-requests', [AdminController::class, 'pendingStudentRequests']);
Route::post('/admin/student-requests/{id}/approve', [AdminController::class, 'approveStudentRequest']);
Route::post('/admin/student-requests/{id}/reject', [AdminController::class, 'rejectStudentRequest']);
Route::post('/parents/notifications/{id}/dismiss', [ParentController::class, 'dismissNotification']);
Route::post('/student-mispronunciations', [ClassEnrollmentController::class, 'logMispronunciation']);
Route::get('/students/{student_id}/mispronunciations', [ClassEnrollmentController::class, 'getStudentMispronunciations']);
Route::post('/student/join-class', [StudentClassController::class, 'joinClass']);
Route::get('/student/{student_id}/classes', [StudentClassController::class, 'myClasses']);
Route::get('/stories', [StoryController::class, 'index']);
Route::post('/stories', [StoryController::class, 'store']);
Route::delete('/stories/{id}', [StoryController::class, 'destroy']);
Route::put('/stories/{id}/update-meta', [StoryController::class, 'updateMeta']);
Route::delete('/classes/{id}', [ClassController::class, 'destroy']);
Route::get('/stories/level/{level}', [StoryController::class, 'getStoriesByLevel']);
Route::get('/stories/{storyId}/quiz', [StoryController::class, 'getQuizByStory']);
Route::post('/stories/{storyId}/quiz', [StoryController::class, 'addQuizToStory']);
Route::post('/stories/pages/{id}/update-audio', [StoryController::class, 'updatePageAudio']);
Route::put('/stories/{story_id}/slides/{slide_index}/update-script', [StoryAudioController::class, 'updateSlideScript']);
Route::put('/story-pages/{id}/audio', [StoryController::class, 'updatePageAudio']);
Route::post('/story-pages/{id}/audio', [StoryController::class, 'updatePageAudio']);
Route::post('/student/progress', [StoryController::class, 'saveProgress']);
Route::get('/student/{student_id}/story/{story_id}/progress', [StoryController::class, 'getProgress']);
Route::get('/student/{student_id}/all-progress', [StoryController::class, 'getAllProgress']);
Route::post('/student/save-reading-progress', [StoryController::class, 'saveReadingProgress']);
Route::post('/stories/{story_id}/slides/{slide_index}/generate-tts', [StoryAudioController::class, 'generateTts']);
Route::get('/teachers/{teacher_id}/mispronunciations', [ClassEnrollmentController::class, 'getTeacherStudentLogs']);
Route::get('/teachers/{teacher_id}/classes', [ClassEnrollmentController::class, 'getTeacherClasses']);
Route::get('/teachers/{teacher_id}/dashboard-summary', [ClassEnrollmentController::class, 'getTeacherDashboardSummary']);
Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
Route::post('/email/resend', [AuthController::class, 'resendVerification']);
Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])->name('verification.verify');
Route::post('/request-student-account', [AuthController::class, 'requestStudentAccount']);
Route::post('/users/{id}/avatar', [AuthController::class, 'uploadAvatar']);
Route::get('/teacher/{teacherId}/alerts', [App\Http\Controllers\AlertController::class, 'getFrustrationAlerts']);
Route::get('/teachers/{id}/alerts', [AlertController::class, 'getFrustrationAlerts']);
Route::post('/parents/enroll', [ParentController::class, 'enroll']);
Route::get('/parents/{parentId}/dashboard', [ParentController::class, 'dashboard']);
Route::post('/student-self-corrections', [SelfCorrectionController::class, 'storeSelfCorrections']);
Route::get('/teachers/{id}/self-corrections', [SelfCorrectionController::class, 'teacherSelfCorrections']);
Route::post('/pages/{page}/multi-script-audio', [StoryController::class, 'generateMultiScriptAudio']);
Route::get('/classes/{id}/available-students', [App\Http\Controllers\Api\ClassController::class, 'getAvailableStudents']);
Route::post('/classes/{id}/bulk-add-students', [App\Http\Controllers\Api\ClassController::class, 'bulkAddStudents']);
Route::get('/teachers/{teacherId}/pending-students', [TeacherEnrollmentController::class, 'pendingStudents']);
Route::post('/teachers/{teacherId}/students/{studentId}/enroll', [TeacherEnrollmentController::class, 'enroll']);
Route::post('/teachers/{teacherId}/students/{studentId}/decline', [TeacherEnrollmentController::class, 'decline']);
Route::get('/test', function () {
    return response()->json([
        'message' => 'Laravel API Connected Successfully'
    ]);
});

Route::get('/get-image', function (\Illuminate\Http\Request $request) {
    $path = $request->query('path');
    $fullPath = storage_path('app/public/' . $path);

    if (file_exists($fullPath)) {
        return response()->file($fullPath, [
            'Access-Control-Allow-Origin' => '*'
        ]);
    }
    return response()->json(['error' => 'File not found'], 404);
});

Route::options('{any}', function () {
    return response('', 200)
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, ngrok-skip-browser-warning, Accept');
})->where('any', '.*');

Route::get('/sections', [\App\Http\Controllers\Api\SectionController::class, 'index']);
Route::post('/sections', [\App\Http\Controllers\Api\SectionController::class, 'store']);
Route::get('/classes/{id}', [ClassController::class, 'show']);
Route::get('/classes/{id}/students', [StudentClassController::class, 'getClassStudents']);
