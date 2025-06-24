<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentRedirectController;
use App\Http\Controllers\InvitationController;

Route::get('/', fn () => redirect('/admin'));

Route::get('/login', fn () => redirect('/admin/login'))->name('login');

// تحميل الملفات المرفقة
Route::get('/download-attachment/{path}', function ($path) {
    $filePath = storage_path('app/public/' . $path);

    if (!file_exists($filePath)) {
        abort(404, 'File not found: ' . $path);
    }

    return response()->download($filePath);
})->middleware('auth')->name('download.attachment')->where('path', '.*');

// --------------------------
// Payment Routes (UI)
// --------------------------
Route::match(['get', 'post'], '/payment/pay', [PaymentController::class, 'pay'])
    ->middleware(['auth', 'throttle:10,1'])
    ->name('payment.pay');

// --------------------------
// PayTabs Integration
// --------------------------
Route::prefix('paytabs')->name('paytabs.')->middleware(['throttle:60,1'])->group(function () {

    // إنشاء عملية دفع
    Route::post('/create-payment', [PaymentController::class, 'createPayment'])
        ->middleware(['throttle:10,1'])
        ->name('create.payment');

    // استقبال Callback من PayTabs - مسارات متعددة
    Route::match(['get', 'post'], '/callback', [PaymentController::class, 'callback'])
        ->name('callback')
        ->withoutMiddleware(['web', 'csrf']);
    
    Route::match(['get', 'post'], '/webhook', [PaymentController::class, 'callback'])
        ->name('webhook')
        ->withoutMiddleware(['web', 'csrf']);

    // التحقق من حالة الدفع - محدود الطلبات
    Route::match(['get', 'post'], '/check-status', [PaymentController::class, 'checkPaymentStatus'])
        ->middleware(['throttle:30,1'])
        ->name('check.status');
    
    // فحص دوري لحالة الدفع
    Route::get('/verify/{transaction_ref}', [PaymentController::class, 'verifyPayment'])
        ->middleware(['throttle:20,1'])
        ->name('verify');

    // لتفعيل الدفع يدويًا في بيئة التطوير فقط
    Route::match(['get', 'post'], '/activate/{transaction_ref}', [PaymentController::class, 'activate'])
        ->withoutMiddleware(['csrf'])
        ->name('activate');
    Route::get('/process-payment/{order_id}', [PaymentController::class, 'processLocalPayment'])
        ->name('process.local');

    // صفحة مؤقتة تعرض نتيجة الدفع وتقوم بإعادة التوجيه تلقائيًا
    Route::get('/result-redirect', function (Request $request) {
        $tranRef = $request->get('tranRef');
        $redirectUrl = route('paytabs.success', ['tranRef' => $tranRef]);
        return view('payment.result-redirect', ['redirectUrl' => $redirectUrl]);
    })->name('result.redirect');

    // دعم إعادة التوجيه من PayTabs إذا كانت غير مباشرة
    Route::get('/payment-redirect', [PaymentRedirectController::class, 'handleRedirect'])->name('payment.redirect');
});

// مسارات النجاح / الفشل / الإلغاء (خارج CSRF)
Route::match(['get', 'post'], '/paytabs/success', [PaymentController::class, 'success'])
    ->withoutMiddleware(['web', 'csrf'])
    ->name('paytabs.success');
Route::match(['get', 'post'], '/paytabs/failed', [PaymentController::class, 'failed'])
    ->withoutMiddleware(['web', 'csrf'])
    ->name('paytabs.failed');
Route::match(['get', 'post'], '/paytabs/cancel', [PaymentController::class, 'cancel'])
    ->withoutMiddleware(['web', 'csrf'])
    ->name('paytabs.cancel');

// إعادة التوجيه المختصر في حالة طلب خارجي
Route::get('/payment-redirect', [PaymentRedirectController::class, 'handleRedirect'])->name('payment.redirect');

// --------------------------
// Invitation Routes
// --------------------------
Route::prefix('invitations')->name('invitations.')->group(function () {
    Route::post('/create', [InvitationController::class, 'create'])->name('create');
    Route::get('/{token}', [InvitationController::class, 'show'])->name('show');
    Route::post('/{token}/accept', [InvitationController::class, 'accept'])->name('accept');
    Route::get('/{token}/reject', [InvitationController::class, 'reject'])->name('reject');
});

// Password Reset Routes for Admin
use Filament\Http\Controllers\Auth\PasswordResetLinkController;
use Filament\Http\Controllers\Auth\NewPasswordController;

Route::get('admin/forgot-password', [PasswordResetLinkController::class, 'create'])->name('filament.admin.auth.password.request');
Route::post('admin/forgot-password', [PasswordResetLinkController::class, 'store'])->name('filament.admin.auth.password.email');

Route::get('admin/reset-password/{token}', [NewPasswordController::class, 'create'])->name('filament.admin.auth.password.reset');
Route::post('admin/reset-password', [NewPasswordController::class, 'store'])->name('filament.admin.auth.password.store');
