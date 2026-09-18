<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminWebController;

// Your login/logout routes
Route::get('/admin/login', [AdminWebController::class, 'showLogin'])->name('admin.login');
Route::post('/admin/login', [AdminWebController::class, 'login'])->name('admin.login.submit');
Route::post('/admin/logout', [AdminWebController::class, 'logout'])->name('admin.logout');

// Your dashboard routes (make sure the reset-password route is in here!)
Route::get('/admin/dashboard', [AdminWebController::class, 'index'])->name('admin.dashboard');
Route::get('/admin/credentials', [AdminWebController::class, 'credentials'])->name('admin.credentials');
Route::post('/admin/students/bulk', [AdminWebController::class, 'bulkCreateStudents'])->name('admin.students.bulk');
Route::post('/admin/parents/bulk', [AdminWebController::class, 'bulkCreateParents'])->name('admin.parents.bulk');
Route::post('/admin/teachers/{id}/approve', [AdminWebController::class, 'approveTeacher'])->name('admin.teachers.approve');
Route::post('/admin/teachers/{id}/revoke', [AdminWebController::class, 'revokeTeacher'])->name('admin.teachers.revoke');


Route::post('/admin/users/{id}/reset-password', [AdminWebController::class, 'resetPassword'])->name('admin.users.reset-password');
Route::post('/admin/students/{id}/reassign-teacher', [App\Http\Controllers\AdminWebController::class, 'reassignTeacher'])->name('admin.students.reassign-teacher');