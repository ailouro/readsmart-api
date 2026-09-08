<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminWebController;

Route::get('/admin', [AdminWebController::class, 'index'])->name('admin.dashboard');
Route::post('/admin/request/{id}/approve', [AdminWebController::class, 'approveRequest'])->name('admin.approve');
Route::post('/admin/request/{id}/reject', [AdminWebController::class, 'rejectRequest'])->name('admin.reject');

Route::get('/', function () {
    return view('welcome');
});
