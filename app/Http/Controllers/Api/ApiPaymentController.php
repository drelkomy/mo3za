<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ApiPaymentController extends Controller
{
    public function callback(Request $request): JsonResponse
    {
        try {
            // سجل بيانات الـ callback للتحقق
            Log::info('PayTabs callback received', [
                'method' => $request->method(),
                'data' => $request->all(),
                'headers' => $request->headers->all(),
                'ip' => $request->ip(),
                'raw_content' => $request->getContent(),
                'content_type' => $request->header('Content-Type')
            ]);

            // PayTabs يرسل cart_id بصيغة payment_xxx أو subscription_xxx أو ORDER-
            $cartId = $request->input('cart_id') ?? $request->input('cartId') ?? null;
            $transactionId = $request->input('tran_ref') ?? $request->input('tranRef') ?? null;
            $respStatus = $request->input('resp_status') ?? $request->input('payment_result.response_status') ?? null;
            $respMessage = $request->input('resp_message') ?? $request->input('payment_result.response_message') ?? 'غير متوفر';

            if (!$cartId) {
                Log::error('Cart ID not found in callback data', $request->all());
                return response()->json(['message' => 'Cart ID not found'], 400);
            }

            Log::info('Processing callback', [
                'cart_id' => $cartId, 
                'resp_status' => $respStatus, 
                'transaction_id' => $transactionId,
                'raw_request_data' => $request->all()
            ]);

            // التحقق من صحة الكول باك باستخدام التوقيع الرقمي
            $payTabsService = new \App\Services\PayTabsService();
            $signature = $request->header('Signature');
            $content = $request->getContent();
            if (!$payTabsService->validateCallback($content, $signature)) {
                Log::error('Invalid callback received', [
                    'request_data' => $request->all(),
                    'signature' => $signature,
                    'content' => $content
                ]);
                return response()->json(['message' => 'Invalid callback'], 400);
            } else {
                Log::info('Callback validation successful', [
                    'signature' => $signature,
                    'content_length' => strlen($content)
                ]);
            }

            // التحقق من حالة الدفع (لن يؤثر على تحديث الحالة بناءً على resp_status)
            if ($transactionId) {
                try {
                    $verification = $payTabsService->verifyPayment($transactionId);
                    if ($verification['success'] && in_array($verification['status'], ['captured', 'Completed', 'A'])) {
                        Log::info('Payment verification successful', [
                            'transaction_id' => $transactionId,
                            'status' => $verification['status']
                        ]);
                        // تحديث حالة الدفع بناءً على التحقق
                        $respStatus = 'A';
                    } else {
                        Log::warning('Payment verification failed or status not authorized, using resp_status from callback', [
                            'transaction_id' => $transactionId,
                            'status' => $verification['status'] ?? 'unknown',
                            'success' => $verification['success'],
                            'resp_status' => $respStatus
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('Error during payment verification, using resp_status from callback', [
                        'transaction_id' => $transactionId,
                        'error' => $e->getMessage(),
                        'resp_status' => $respStatus
                    ]);
                }
            }

            // التحقق مما إذا كان cart_id يحتوي على payment_ أو subscription_ أو ORDER-
            if (strpos($cartId, 'payment_') === 0 || strpos($cartId, 'ORDER-') === 0) {
                $payment = \App\Models\Payment::where('order_id', $cartId)->first();
                
                if (!$payment) {
                    // البحث عن تطابق جزئي إذا لم يتم العثور على تطابق مباشر
                    $payment = \App\Models\Payment::where('order_id', 'LIKE', '%' . $cartId . '%')
                        ->orWhere('order_id', 'LIKE', '%' . str_replace('payment_', '', $cartId) . '%')
                        ->first();
                    
                    if (!$payment) {
                        Log::error('Payment not found even with partial match', [
                            'cart_id' => $cartId,
                            'possible_matches' => \App\Models\Payment::where('order_id', 'LIKE', '%' . $cartId . '%')->pluck('order_id')->toArray()
                        ]);
                        // إنشاء سجل دفع جديد إذا لم يتم العثور على تطابق
                        $payment = \App\Models\Payment::create([
                            'order_id' => $cartId,
                            'user_id' => 0, // يمكن تحديثه لاحقًا إذا لزم الأمر
                            'amount' => $request->input('tran_amount') ?? 0,
                            'currency' => $request->input('tran_currency') ?? 'SAR',
                            'status' => 'pending',
                            'payment_method' => 'online'
                        ]);
                        Log::info('Created new payment record due to no match found', [
                            'cart_id' => $cartId,
                            'new_payment_id' => $payment->id
                        ]);
                    } else {
                        Log::info('Payment found with partial match', [
                            'cart_id' => $cartId,
                            'matched_order_id' => $payment->order_id
                        ]);
                    }
                }
                
                Log::info('Payment record found', ['payment_id' => $payment->id, 'current_status' => $payment->status]);
                
                // تجنب معالجة نفس الدفع مرتين
                if ($payment->status === 'completed') {
                    Log::info('Payment already processed', [
                        'order_id' => $cartId,
                        'transaction_id' => $transactionId
                    ]);
                    return response()->json([
                        'message' => 'تم معالجة الدفع بالفعل',
                        'status' => 'success',
                        'payment_id' => $payment->id
                    ]);
                }

                // معالجة حالات الاستجابة المختلفة من PayTabs
                switch ($respStatus) {
                    case 'A': // Authorized = نجح الدفع
                        $updated = $payment->update([
                            'status' => 'completed',
                            'transaction_id' => $transactionId,
                            'amount' => $request->input('tran_amount') ?? $payment->amount,
                            'currency' => $request->input('tran_currency') ?? $payment->currency,
                            'payment_method' => $request->input('tran_method') ?? 'online'
                        ]);
                        
                        Log::info('Payment status updated to completed', ['payment_id' => $payment->id, 'updated' => $updated]);
                        
                        // إنشاء اشتراك جديد بناءً على الدفع الناجح
                        if ($payment->package_id) {
                            try {
                                $subscription = $this->createSubscriptionFromPayment($payment);
                                Log::info('Subscription creation result', [
                                    'success' => $subscription ? true : false,
                                    'subscription_id' => $subscription ? $subscription->id : null
                                ]);
                            } catch (\Exception $e) {
                                Log::error('Failed to create subscription', ['payment_id' => $payment->id, 'error' => $e->getMessage()]);
                            }
                        }
                        return response()->json([
                            'message' => 'تم تفعيل الاشتراك بنجاح',
                            'status' => 'success',
                            'payment_id' => $payment->id,
                            'subscription_id' => $subscription->id ?? null
                        ]);
                    case 'H': // Hold = الحظر
                        $updated = $payment->update([
                            'status' => 'on_hold',
                            'transaction_id' => $transactionId
                        ]);
                        Log::warning('Payment on hold', ['payment_id' => $payment->id, 'updated' => $updated, 'message' => $respMessage]);
                        return response()->json([
                            'message' => 'الدفع معلق بسبب الحظر',
                            'status' => 'on_hold',
                            'payment_id' => $payment->id
                        ]);
                    case 'P': // Pending = قيد الانتظار
                        $updated = $payment->update([
                            'status' => 'pending',
                            'transaction_id' => $transactionId
                        ]);
                        Log::info('Payment pending', ['payment_id' => $payment->id, 'updated' => $updated, 'message' => $respMessage, 'request_data' => $request->all()]);
                        return response()->json([
                            'message' => 'الدفع قيد الانتظار',
                            'status' => 'pending',
                            'payment_id' => $payment->id
                        ]);
                    case 'V': // Voided = ملغى
                    case 'E': // Error = خطأ
                        $updated = $payment->update([
                            'status' => 'failed',
                            'transaction_id' => $transactionId
                        ]);
                        Log::error('Payment failed or voided', ['payment_id' => $payment->id, 'updated' => $updated, 'status' => $respStatus, 'message' => $respMessage, 'request_data' => $request->all()]);
                        return response()->json([
                            'message' => 'فشل في الدفع: ' . $respMessage,
                            'status' => 'failed',
                            'payment_id' => $payment->id
                        ], 400);
                    default:
                        Log::error('Unknown payment status', ['payment_id' => $payment->id, 'status' => $respStatus, 'message' => $respMessage, 'request_data' => $request->all()]);
                        return response()->json([
                            'message' => 'حالة دفع غير معروفة',
                            'status' => 'unknown',
                            'payment_id' => $payment->id
                        ], 400);
                }
            } else if (strpos($cartId, 'subscription_') === 0) {
                $subscriptionId = str_replace('subscription_', '', $cartId);
                $subscription = Subscription::find($subscriptionId);
                
                if (!$subscription) {
                    Log::error('Subscription not found', ['cart_id' => $cartId, 'subscription_id' => $subscriptionId]);
                    return response()->json([
                        'message' => 'الاشتراك غير موجود',
                        'status' => 'error'
                    ], 404);
                }
                
                Log::info('Subscription record found', ['subscription_id' => $subscriptionId, 'current_status' => $subscription->status]);
                
                // معالجة حالات الاستجابة المختلفة من PayTabs
                switch ($respStatus) {
                    case 'A': // Authorized = نجح الدفع
                        $updated = $subscription->update([
                            'status' => 'active',
                            'transaction_id' => $transactionId
                        ]);
                        Log::info('Subscription activated', ['subscription_id' => $subscriptionId, 'updated' => $updated]);
                        return response()->json([
                            'message' => 'تم تفعيل الاشتراك بنجاح',
                            'status' => 'success',
                            'subscription_id' => $subscriptionId
                        ]);
                    case 'H': // Hold = الحظر
                        $updated = $subscription->update([
                            'status' => 'on_hold',
                            'transaction_id' => $transactionId
                        ]);
                        Log::warning('Subscription on hold', ['subscription_id' => $subscriptionId, 'updated' => $updated, 'message' => $respMessage]);
                        return response()->json([
                            'message' => 'الاشتراك معلق بسبب الحظر',
                            'status' => 'on_hold',
                            'subscription_id' => $subscriptionId
                        ]);
                    case 'P': // Pending = قيد الانتظار
                        $updated = $subscription->update([
                            'status' => 'pending',
                            'transaction_id' => $transactionId
                        ]);
                        Log::info('Subscription pending', ['subscription_id' => $subscriptionId, 'updated' => $updated, 'message' => $respMessage]);
                        return response()->json([
                            'message' => 'الاشتراك قيد الانتظار',
                            'status' => 'pending',
                            'subscription_id' => $subscriptionId
                        ]);
                    case 'V': // Voided = ملغى
                    case 'E': // Error = خطأ
                        $updated = $subscription->update([
                            'status' => 'failed',
                            'transaction_id' => $transactionId
                        ]);
                        Log::error('Payment failed or voided', ['subscription_id' => $subscriptionId, 'updated' => $updated, 'status' => $respStatus, 'message' => $respMessage]);
                        return response()->json([
                            'message' => 'فشل في الدفع: ' . $respMessage,
                            'status' => 'failed',
                            'subscription_id' => $subscriptionId
                        ], 400);
                    default:
                        Log::error('Unknown payment status', ['subscription_id' => $subscriptionId, 'status' => $respStatus, 'message' => $respMessage]);
                        return response()->json([
                            'message' => 'حالة دفع غير معروفة',
                            'status' => 'unknown',
                            'subscription_id' => $subscriptionId
                        ], 400);
                }
            }
        } catch (\Exception $e) {
            Log::error('Callback processing failed', [
                'error' => $e->getMessage(),
                'order_id' => $cartId ?? 'N/A',
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Callback processing failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * إنشاء اشتراك جديد من عملية دفع ناجحة
     *
     * @param \App\Models\Payment $payment
     * @return \App\Models\Subscription|null
     */
    protected function createSubscriptionFromPayment(\App\Models\Payment $payment)
    {
        try {
            // تأكد من أن الدفع مرتبط بباقة وأنه مكتمل
            if (!$payment->package_id || $payment->status !== 'completed') {
                Log::warning('Cannot create subscription: Payment is not completed or not associated with a package', [
                    'payment_id' => $payment->id,
                    'status' => $payment->status,
                    'package_id' => $payment->package_id
                ]);
                return null;
            }

            // تحقق من وجود اشتراك سابق لهذا الدفع
            if ($payment->subscription()->exists()) {
                Log::info('Subscription already exists for this payment', [
                    'payment_id' => $payment->id,
                    'subscription_id' => $payment->subscription->id
                ]);
                return $payment->subscription;
            }

            // الحصول على الباقة
            $package = $payment->package;
            if (!$package) {
                Log::error('Package not found for payment', [
                    'payment_id' => $payment->id,
                    'package_id' => $payment->package_id
                ]);
                return null;
            }

            // تعيين تاريخ البداية
            $startDate = now();

            // إنشاء الاشتراك
            $subscription = new \App\Models\Subscription([
                'user_id' => $payment->user_id,
                'package_id' => $package->id,
                'payment_id' => $payment->id,
                'status' => 'active',
                'price_paid' => $payment->amount,
                'tasks_created' => 0,
                'max_tasks' => $package->max_tasks,
                'max_milestones_per_task' => $package->max_milestones_per_task,
                'start_date' => $startDate,
                'end_date' => $startDate->copy()->addYear(), // اشتراك لمدة عام
            ]);

            $subscription->save();

            Log::info('Subscription created successfully', [
                'subscription_id' => $subscription->id,
                'payment_id' => $payment->id,
                'user_id' => $payment->user_id,
                'start_date' => $startDate->toDateString(),
                'max_tasks' => $package->max_tasks,
                'max_milestones_per_task' => $package->max_milestones_per_task
            ]);

            return $subscription;
        } catch (\Exception $e) {
            Log::error('Failed to create subscription', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }
    
    public function success($paymentId)
    {
        $payment = \App\Models\Payment::find($paymentId);
        
        if (!$payment) {
            Log::error('Payment record not found', ['payment_id' => $paymentId]);
            return response()->json([
                'status' => 'error',
                'message' => 'سجل الدفع غير موجود'
            ], 404);
        }
        
        Log::info('Payment record found', ['payment_id' => $paymentId, 'current_status' => $payment->status]);
        
        // تحديث حالة الدفع إذا لم تكن مكتملة بالفعل
        if ($payment->status !== 'completed') {
            $updated = $payment->update([
                'status' => 'completed',
                'transaction_id' => $payment->transaction_id ?? 'mobile_payment_' . uniqid()
            ]);
            
            Log::info('Payment status update attempted', ['payment_id' => $paymentId, 'updated' => $updated, 'status' => $payment->status]);
            
            // إنشاء اشتراك جديد بناءً على الدفع الناجح باستخدام الدالة المخصصة
            $subscription = $this->createSubscriptionFromPayment($payment);
            if ($subscription) {
                Log::info('Subscription created', ['subscription_id' => $subscription->id, 'user_id' => $payment->user_id]);
            } else {
                Log::error('Failed to create subscription in success method', ['payment_id' => $payment->id]);
            }
        } else {
            // إذا كان الدفع مكتملاً بالفعل، جلب الاشتراك المرتبط إن وجد
            $subscription = Subscription::where('user_id', $payment->user_id)
                ->where('package_id', $payment->package_id)
                ->where('status', 'active')
                ->orderBy('created_at', 'desc')
                ->first();
            Log::info('Subscription lookup for existing payment', ['payment_id' => $paymentId, 'subscription_id' => $subscription ? $subscription->id : null]);
        }
        
        return response()->json([
            'status' => 'success',
            'message' => 'تمت عملية الدفع بنجاح',
            'payment_id' => $payment->id,
            'subscription_id' => $subscription ? $subscription->id : null
        ]);
    }
    
    public function cancel($paymentId)
    {
        $payment = \App\Models\Payment::find($paymentId);
        
        if (!$payment) {
            return response()->json([
                'status' => 'error',
                'message' => 'سجل الدفع غير موجود'
            ], 404);
        }
        
        // تحديث حالة الدفع إلى ملغى إذا لزم الأمر
        if ($payment->status !== 'cancelled') {
            $payment->update([
                'status' => 'cancelled'
            ]);
        }
        
        return response()->json([
            'status' => 'cancelled',
            'message' => 'تم إلغاء عملية الدفع',
            'payment_id' => $payment->id
        ]);
    }
}
