<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\TrackingController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('welcome');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/tracking/latest', [TrackingController::class, 'index'])->name('tracking.latest');

    Route::get('/user', [UserController::class, 'index'])->name('user.list')->middleware('role:admin');
    Route::post('/user', [UserController::class, 'store'])->name('user.store')->middleware('role:admin');
    Route::put('/user/{id}', [UserController::class, 'update'])->name('user.update')->middleware('role:admin');
    Route::delete('/user/{id}', [UserController::class, 'destroy'])->name('user.destroy')->middleware('role:admin');

    Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance')->middleware(['role:admin', 'check.attendance']);
    Route::get('/attendance/export', [AttendanceController::class, 'export'])->name('attendance.export')->middleware(['role:admin', 'check.attendance']);
});

Route::get('/recognize', function () {
    return Inertia::render('recognize/Index');
})->name('attendance.recognize')->middleware('check.attendance');

Route::middleware('auth')->group(function () {
    Route::post('/attendance/store', [AttendanceController::class, 'store'])->name('attendance.store');
    
    Route::get('/deliveries', [DeliveryController::class, 'index'])->name('deliveries.index');
    Route::get('/deliveries/{delivery}', [DeliveryController::class, 'show'])->name('deliveries.show');
    Route::post('/deliveries', [DeliveryController::class, 'store'])->name('deliveries.store');
    Route::patch('/deliveries/{delivery}/status', [DeliveryController::class, 'updateStatus'])->name('deliveries.updateStatus');
    
    Route::get('/admin/deliveries', [DeliveryController::class, 'adminIndex'])->name('admin.deliveries.index');

    Route::get('/profile', [ProfileController::class, 'Index'])->name('profile.Index');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';