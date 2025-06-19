<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentRedirectController;
use App\Http\Controllers\InvitationController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| All routes related to payments and subscriptions
|
*/

Route::get('/', fn () => redirect('/app'));

// --------------------------
// Payment Routes (UI-based)
// --------------------------
Route::match(['get', 'post'], '/payment/pay', [PaymentController::class, 'pay'])
    ->name('payment.pay');


// --------------------------
// PayTabs Integration Routes
// --------------------------
Route::prefix('paytabs')->name('paytabs.')->group(function () {

    // إنشاء صفحة دفع جديدة
    Route::post('/create-payment', [PaymentController::class, 'createPayment'])
        ->name('create.payment');

    // استقبال Callback من PayTabs (POST و GET)
    Route::match(['post', 'get'], '/callback', [PaymentController::class, 'callback'])
        ->name('callback');

    // التوجيه بعد الدفع (نجاح - فشل - إلغاء)
    Route::get('/success', [PaymentController::class, 'success'])->name('success');
    Route::get('/failed', [PaymentController::class, 'failed'])->name('failed');
    Route::get('/cancel', [PaymentController::class, 'cancel'])->name('cancel');

    // تفعيل الدفع بعد النجاح
    Route::match(['get', 'post'], '/activate/{transaction_ref}', [PaymentController::class, 'activate'])
        ->name('activate');

    // التحقق من حالة الدفع
    Route::match(['get', 'post'], '/check-status', [PaymentController::class, 'checkPaymentStatus'])
        ->name('check.status');

    // معالجة الدفع يدوياً (مفيد للبيئة المحلية)
    Route::get('/process-payment/{order_id}', [PaymentController::class, 'processLocalPayment'])
        ->name('process.local');

    // صفحة مؤقتة لعرض نتيجة الدفع مع إعادة التوجيه تلقائياً
    Route::get('/result-redirect', function (Request $request) {
        $tranRef = $request->get('tranRef');
        $redirectUrl = route('paytabs.success', ['tranRef' => $tranRef]);
        return view('payment.result-redirect', ['redirectUrl' => $redirectUrl]);
    })->name('result.redirect');

    // معالجة إعادة التوجيه اليدوي
    Route::get('/payment-redirect', [PaymentRedirectController::class, 'handleRedirect'])
        ->name('payment.redirect');
});

// مسار خارجي مختصر لإعادة التوجيه
Route::get('/payment-redirect', [PaymentRedirectController::class, 'handleRedirect'])
    ->name('payment.redirect');

// --------------------------
// Invitation Routes
// --------------------------
Route::prefix('invitations')->name('invitations.')->group(function () {
    // إنشاء دعوة جديدة
    Route::post('/create', [InvitationController::class, 'create'])->name('create');
    
    // عرض صفحة قبول الدعوة
    Route::get('/{token}', [InvitationController::class, 'show'])->name('show');
    
    // قبول الدعوة
    Route::post('/{token}/accept', [InvitationController::class, 'accept'])->name('accept');
    
    // رفض الدعوة
    Route::get('/{token}/reject', [InvitationController::class, 'reject'])->name('reject');
});