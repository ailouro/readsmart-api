<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminWebController;
use App\Http\Middleware\EnsureAdmin;

/*
|--------------------------------------------------------------------------
| Admin web dashboard
|--------------------------------------------------------------------------
| The old routes here were completely unauthenticated — anyone who knew
| the URL could open /admin and approve accounts. Everything except the
| login screen now sits behind the EnsureAdmin middleware.
|
| The old `?key=ADMIN_PANEL_KEY` pages in AdminController (pendingApprovals,
| approveAccount, pendingStudentRequests, approveStudentRequest,
| rejectStudentRequest) are superseded by this dashboard. Remove those
| routes from routes/api.php once you've confirmed this works.
*/

Route::get('/admin/login', [AdminWebController::class, 'showLogin'])->name('admin.login');
Route::post('/admin/login', [AdminWebController::class, 'login'])->name('admin.login.submit');

Route::middleware(EnsureAdmin::class)->group(function () {
    Route::post('/admin/logout', [AdminWebController::class, 'logout'])->name('admin.logout');

    Route::get('/admin', [AdminWebController::class, 'index'])->name('admin.dashboard');

    Route::post('/admin/students/bulk', [AdminWebController::class, 'bulkCreateStudents'])
        ->name('admin.students.bulk');

    Route::post('/admin/parents/bulk', [AdminWebController::class, 'bulkCreateParents'])
        ->name('admin.parents.bulk');

    Route::post('/admin/teachers/{id}/approve', [AdminWebController::class, 'approveTeacher'])
        ->name('admin.teachers.approve');
    Route::post('/admin/teachers/{id}/revoke', [AdminWebController::class, 'revokeTeacher'])
        ->name('admin.teachers.revoke');

    Route::get('/admin/credentials', [AdminWebController::class, 'credentials'])
        ->name('admin.credentials');
});

Route::get('/', function () {
    return view('welcome');
});