<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TeamJoinRequestController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// مسارات المصادقة العامة - 5 طلبات في الدقيقة للضيوف
Route::middleware('throttle:guest')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

// المسارات المحمية بالمصادقة - 8 طلبات في الدقيقة للمستخدمين
Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    // معلومات المستخدم الحالي - مع cache
    Route::get('/me', [AuthController::class, 'me']);

    // جلب بيانات البروفايل - مع cache
    Route::get('/profile', [AuthController::class, 'showProfile']);

    // تحديث بيانات البروفايل
    Route::post('/profile', [AuthController::class, 'updateProfile']);
    
    // Team Join Requests - طلبات الانضمام
    Route::post('/join-request', [TeamJoinRequestController::class, 'store']); // إرسال طلب
    Route::get('/join-requests', [TeamJoinRequestController::class, 'index']); // طلباتي المرسلة - مع cache
    Route::get('/join-requests/received', [TeamJoinRequestController::class, 'received']); // طلبات مرسلة لي - مع cache
    Route::patch('/join-requests/{joinRequest}', [TeamJoinRequestController::class, 'update']); // قبول/رفض
    Route::delete('/join-requests/{joinRequest}', [TeamJoinRequestController::class, 'destroy']); // حذف
    
    // تسجيل الخروج
    Route::post('/logout', [AuthController::class, 'logout']);
});