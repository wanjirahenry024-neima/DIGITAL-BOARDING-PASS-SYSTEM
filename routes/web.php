<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\BoardingPassController as AdminBoardingPassController;
use App\Http\Controllers\Admin\BookingController as AdminBookingController;
use App\Http\Controllers\Admin\FlightController as AdminFlightController;
use App\Http\Controllers\Admin\SystemReportController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\VerificationRecordController as AdminVerificationRecordController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BoardingPassController;
use App\Http\Controllers\BoardingPassVerificationController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CheckInController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PassengerFlightController;
use App\Http\Controllers\Staff\GateTerminalController;
use App\Http\Controllers\Staff\StaffDashboardController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware('guest')->group(function (): void {
    Route::get('/register', [AuthController::class, 'showRegistration'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.store');
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1')->name('login.store');
    Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->middleware('throttle:6,1')->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'showResetPassword'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');
});

Route::middleware(['auth', 'passenger'])->group(function (): void {
    Route::view('/dashboard', 'dashboard')->name('dashboard');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])
        ->name('notifications.read');
    Route::get('/flights', [PassengerFlightController::class, 'index'])->name('flights.index');
    Route::get('/flights/{flight}', [PassengerFlightController::class, 'show'])->name('flights.show');
    Route::get('/bookings', [BookingController::class, 'index'])->name('bookings.index');
    Route::post('/bookings', [BookingController::class, 'store'])->name('bookings.store');
    Route::get('/bookings/{booking}/confirmation', [BookingController::class, 'confirmation'])->name('bookings.confirmation');
    Route::get('/bookings/{booking}', [BookingController::class, 'show'])->name('bookings.show');
    Route::patch('/bookings/{booking}/cancel', [BookingController::class, 'cancel'])->name('bookings.cancel');
    Route::get('/bookings/{booking}/check-in', [CheckInController::class, 'show'])->name('check-ins.create');
    Route::post('/bookings/{booking}/check-in', [CheckInController::class, 'store'])->name('check-ins.store');
    Route::get('/check-ins/{checkIn}/confirmation', [CheckInController::class, 'confirmation'])->name('check-ins.confirmation');
    Route::get('/boarding-passes/{boardingPass}', [BoardingPassController::class, 'show'])->name('boarding-passes.show');
    Route::get('/boarding-passes/{boardingPass}/download', [BoardingPassController::class, 'download'])->name('boarding-passes.download');
});

Route::prefix('staff')->name('staff.')->middleware(['auth', 'staff'])->group(function (): void {
    Route::get('/', StaffDashboardController::class)->name('dashboard');
    Route::get('/gate/status', [GateTerminalController::class, 'status'])->name('gate.status');
    Route::post('/gate/synchronize', [GateTerminalController::class, 'synchronize'])->name('gate.synchronize');
    Route::post('/gate/closeout', [GateTerminalController::class, 'closeout'])->name('gate.closeout');
    Route::post('/boarding-passes/verify', [BoardingPassVerificationController::class, 'scan'])
        ->middleware('throttle:30,1')
        ->name('boarding-passes.verify');
    Route::post('/boarding-pass-verifications/{verification}/approve', [BoardingPassVerificationController::class, 'approve'])
        ->middleware('throttle:30,1')
        ->name('boarding-pass-verifications.approve');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});

Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin'])->group(function (): void {
    Route::get('/', AdminDashboardController::class)->name('dashboard');
    Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
    Route::patch('/users/{user}', [AdminUserController::class, 'update'])->name('users.update');
    Route::get('/boarding-passes', [AdminBoardingPassController::class, 'index'])->name('boarding-passes.index');
    Route::get('/verification-records', [AdminVerificationRecordController::class, 'index'])->name('verification-records.index');
    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    Route::get('/reports/export', [SystemReportController::class, 'export'])->name('reports.export');
    Route::get('/reports', [SystemReportController::class, 'index'])->name('reports.index');
    Route::view('/profile', 'admin.profile')->name('profile');
    Route::resource('flights', AdminFlightController::class);
    Route::patch('flights/{flight}/status', [AdminFlightController::class, 'updateStatus'])->name('flights.status');
    Route::get('bookings', [AdminBookingController::class, 'index'])->name('bookings.index');
    Route::get('bookings/{booking}', [AdminBookingController::class, 'show'])->name('bookings.show');
    Route::patch('bookings/{booking}/cancel', [AdminBookingController::class, 'cancel'])->name('bookings.cancel');
});
