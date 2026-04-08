<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AttendanceController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('welcome');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/user', [UserController::class, 'index'])->name('user.list')->middleware('role:other,pm');
    Route::post('/user', [UserController::class, 'store'])->name('user.store')->middleware('role:other,pm');
    Route::put('/user/{id}', [UserController::class, 'update'])->name('user.update')->middleware('role:other,pm');
    Route::patch('/user/{id}/wfa', [UserController::class, 'toggleWfa'])->name('user.toggleWfa')->middleware('role:other,pm');
    Route::delete('/user/{id}', [UserController::class, 'destroy'])->name('user.destroy')->middleware('role:other,pm');

    Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance')->middleware(['role:other', 'check.attendance']);
    Route::get('/attendance/export', [AttendanceController::class, 'export'])->name('attendance.export')->middleware(['role:other', 'check.attendance']);
});

Route::get('/recognize', function () {
    return Inertia::render('recognize/Index');
})->name('attendance.recognize')->middleware('check.attendance');

Route::post('/attendance/store', [AttendanceController::class, 'store'])->name('attendance.store')->middleware('check.attendance');

Route::post('/attendance/toggle', [AttendanceController::class, 'toggleStatus'])->name('attendance.toggle');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'Index'])->name('profile.Index');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';