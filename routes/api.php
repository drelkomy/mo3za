<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;

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
Route::middleware('throttle:5,1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/password/reset-link', [\App\Http\Controllers\Api\PasswordResetController::class, 'sendResetLink']);
});

// المسارات المحمية بالمصادقة - 60 طلبات في الدقيقة للمستخدمين
Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    // معلومات المستخدم الحالي - مع cache
    Route::get('/me', [AuthController::class, 'me']);

    // جلب بيانات البروفايل - مع cache
    Route::get('/profile', [AuthController::class, 'showProfile']);

    // تحديث بيانات البروفايل
    Route::post('/profile', [AuthController::class, 'updateProfile']);

    // Team Management - إدارة الفرق
    Route::post('/team/create', [TeamController::class, 'create']); // إنشاء فريق
    Route::get('/my-team', [TeamController::class, 'myTeam'])
        ->middleware('throttle:30,1'); // عرض فريقي - 30 طلب في الدقيقة
    Route::post('/team/update-name', [TeamController::class, 'updateName']); // تعديل اسم الفريق
    Route::post('/team/remove-member', [TeamController::class, 'removeMember']); // حذف عضو
    
    // Task Management - إدارة المهام
    Route::post('/team/create-task', [TeamController::class, 'createTask'])
        ->middleware('throttle:10,1'); // 10 مهام في الدقيقة
    Route::get('/team/tasks', [TeamController::class, 'getTeamTasks']); // عرض مهام الفريق
    Route::get('/team/rewards', [TeamController::class, 'getTeamRewards']); // عرض مكافآت الفريق
    Route::get('/team/member-stats', [TeamController::class, 'getMemberStats']); // إحصائيات عضو محدد
    Route::get('/my-tasks', [\App\Http\Controllers\Api\TaskController::class, 'myTasks']); // عرض مهامي الشخصية
    Route::post('/tasks/complete-stage', [\App\Http\Controllers\Api\TaskController::class, 'completeStage'])
        ->middleware('throttle:20,1'); // 20 مرحلة في الدقيقة
    Route::post('/tasks/close', [\App\Http\Controllers\Api\TaskController::class, 'closeTask'])
        ->middleware('throttle:10,1'); // 10 إغلاق في الدقيقة
    Route::get('/my-rewards', [\App\Http\Controllers\Api\TaskController::class, 'myRewards']); // عرض مكافآتي

    // Packages & Subscriptions - الباقات والاشتراكات
    Route::get('/packages', [PackageController::class, 'index']); // عرض الباقات المدفوعة
    Route::get('/trial-package', [PackageController::class, 'trial']); // عرض الباقة التجريبية
    Route::post('/subscribe', [PackageController::class, 'subscribe']); // الاشتراك في باقة
    
    // Payment History - تاريخ المدفوعات
    Route::get('/payments', [\App\Http\Controllers\Api\PaymentHistoryController::class, 'index']); // عرض تاريخ المدفوعات
    
    // Subscription Management - إدارة الاشتراكات
    Route::get('/subscriptions', [\App\Http\Controllers\Api\SubscriptionController::class, 'index']); // عرض الاشتراكات
    Route::post('/subscriptions/cancel', [\App\Http\Controllers\Api\SubscriptionController::class, 'cancel']); // إلغاء اشتراك
    Route::get('/trial-status', [\App\Http\Controllers\Api\SubscriptionController::class, 'trialStatus']); // حالة الاشتراك التجريبي
    Route::get('/current-subscription', [\App\Http\Controllers\Api\SubscriptionController::class, 'currentSubscription']); // اشتراكي الحالي
    
    // Team Invitations - دعوات الفريق
    Route::post('/invitations/send', [\App\Http\Controllers\Api\InvitationController::class, 'send'])
        ->middleware('throttle:5,1'); // إرسال دعوة
    Route::post('/invitations/respond', [\App\Http\Controllers\Api\InvitationController::class, 'respond']); // قبول/رفض
    Route::get('/team/invitations', [\App\Http\Controllers\Api\InvitationController::class, 'teamInvitations']); // دعوات الفريق
    Route::get('/my-invitations', [\App\Http\Controllers\Api\InvitationController::class, 'myInvitations']); // دعواتي
    Route::post('/invitations/delete', [\App\Http\Controllers\Api\InvitationController::class, 'delete']); // حذف دعوة
    

    // Payment Callbacks - استقبال نتائج الدفع
    Route::post('/payment/callback', [\App\Http\Controllers\Api\ApiPaymentController::class, 'callback']); // webhook من بوابة الدفع
    Route::get('/payment/success/{subscription}', [\App\Http\Controllers\Api\ApiPaymentController::class, 'success']); // نجح الدفع
    Route::get('/payment/cancel/{subscription}', [\App\Http\Controllers\Api\ApiPaymentController::class, 'cancel']); // إلغاء الدفع

    // تسجيل الخروج
    Route::post('/logout', [AuthController::class, 'logout']);
});

