<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminWebController;
use App\Http\Middleware\EnsureAdmin;

Route::get('/setup-admin', function () {
    $user = User::firstOrCreate(
        ['email' => 'admin@readsmart.com'],
        [
            'name' => 'Admin',
            'first_name' => 'System',
            'last_name' => 'Admin',
            'password' => Hash::make('admin1234'),
            'role' => 'admin',
            'email_verified_at' => now(),
        ]
    );
    
    // Update password just in case the account existed but had the wrong password
    $user->password = Hash::make('admin1234');
    $user->role = 'admin';
    $user->save();

    return 'Admin account is ready! Go back to the login page and try admin@readsmart.com / admin1234';
});

Route::get('/admin/login', [AdminWebController::class, 'showLogin'])->name('admin.login');
Route::post('/admin/login', [AdminWebController::class, 'login'])->name('admin.login.submit');

Route::middleware(EnsureAdmin::class)->group(function () {
    Route::post('/admin/logout', [AdminWebController::class, 'logout'])->name('admin.logout');

    Route::get('/admin', [AdminWebController::class, 'index'])->name('admin.dashboard');

    Route::post('/admin/students/bulk', [AdminWebController::class, 'bulkCreateStudents'])
        ->name('admin.students.bulk');
    Route::post('/students/{id}/reassign-teacher', [AdminWebController::class, 'reassignTeacher'])
    ->name('students.reassign-teacher');
    Route::post('/users/{id}/reset-password', [AdminWebController::class, 'resetPassword'])
    ->name('users.reset-password');
    
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