<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TeamJoinRequestController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\PackageController;

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
    Route::post('/password/reset-link', [\App\Http\Controllers\Api\PasswordResetController::class, 'sendResetLink']);
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
    Route::post('/join-requests/update-status', [TeamJoinRequestController::class, 'updateStatus']); // قبول/رفض باستخدام POST
    Route::delete('/join-requests/{joinRequest}', [TeamJoinRequestController::class, 'destroy']); // حذف
    Route::post('/join-requests/delete', [TeamJoinRequestController::class, 'deleteRequest']); // حذف باستخدام POST

    // Team Management - إدارة الفرق
    Route::get('/my-team', [TeamController::class, 'myTeam']); // عرض فريقي
    Route::post('/team/remove-member', [TeamController::class, 'removeMember']); // حذف عضو

    // Packages & Subscriptions - الباقات والاشتراكات
    Route::get('/packages', [PackageController::class, 'index']); // عرض الباقات المدفوعة
    Route::get('/trial-package', [PackageController::class, 'trial']); // عرض الباقة التجريبية
    Route::post('/subscribe', [PackageController::class, 'subscribe']); // الاشتراك في باقة
    
    // Payment History - تاريخ المدفوعات
    Route::get('/payments', [\App\Http\Controllers\Api\PaymentHistoryController::class, 'index']); // عرض تاريخ المدفوعات
    
    // Subscription Management - إدارة الاشتراكات
    Route::get('/subscriptions', [\App\Http\Controllers\Api\SubscriptionController::class, 'index']); // عرض الاشتراكات
    Route::post('/subscriptions/cancel', [\App\Http\Controllers\Api\SubscriptionController::class, 'cancel']); // إلغاء اشتراك
    
    // Payment Callbacks - استقبال نتائج الدفع
    Route::post('/payment/callback', [\App\Http\Controllers\Api\PaymentController::class, 'callback']); // webhook من بوابة الدفع
    Route::get('/payment/success/{subscription}', [\App\Http\Controllers\Api\PaymentController::class, 'success']); // نجح الدفع
    Route::get('/payment/cancel/{subscription}', [\App\Http\Controllers\Api\PaymentController::class, 'cancel']); // إلغاء الدفع

    // تسجيل الخروج
    Route::post('/logout', [AuthController::class, 'logout']);
});

