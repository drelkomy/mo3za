<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;

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

// مسارات المصادقة العامة
Route::post('/login', [AuthController::class, 'login']);

// المسارات المحمية بالمصادقة
Route::middleware('auth:sanctum')->group(function () {
    // معلومات المستخدم الحالي
    Route::get('/me', [AuthController::class, 'me']);

    // جلب بيانات البروفايل
    Route::get('/profile', [AuthController::class, 'showProfile']);

    // تحديث بيانات البروفايل
    Route::post('/profile', [AuthController::class, 'updateProfile']);
    
    // تسجيل الخروج
    Route::post('/logout', [AuthController::class, 'logout']);
});