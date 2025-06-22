<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\PayTabsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Models\Package;
use App\Models\Payment;

class PaymentController extends Controller
{
    protected $payTabsService;

    public function __construct(PayTabsService $payTabsService)
    {
        $this->payTabsService = $payTabsService;
    }

    /**
     * إنشاء صفحة دفع جديدة
     */
    /**
     * Create a new payment transaction
     *
     * @param Request $request Payment request data
     * @return JsonResponse Payment response
     */
    public function createPayment(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'amount' => 'required|numeric|min:0.01',
                'currency' => 'required|string|max:3',
                'customer_name' => 'required|string|max:255',
                'customer_email' => 'required|email',
                'customer_phone' => 'required|string',
                'order_id' => 'required|string|unique:payments,order_id',
                'description' => 'nullable|string'
            ]);

            $paymentData = [
                'amount' => $request->amount,
                'currency' => $request->currency,
                'customer_name' => $request->customer_name,
                'customer_email' => $request->customer_email,
                'customer_phone' => $request->customer_phone,
                'order_id' => $request->order_id,
                'description' => $request->description ?? 'Payment for order ' . $request->order_id,
            ];

            $response = $this->payTabsService->createPaymentPage($paymentData);

            if ($response['success']) {
                // Log successful payment creation
                Log::info('Payment created successfully', [
                    'order_id' => $request->order_id,
                    'amount' => $request->amount,
                    'currency' => $request->currency
                ]);

                return response()->json([
                    'success' => true,
                    'payment_url' => $response['payment_url'],
                    'transaction_id' => $response['transaction_id']
                ]);
            }

            throw new \RuntimeException($response['message'] ?? 'Failed to create payment');

        } catch (\Exception $e) {
            Log::error('Payment creation failed', [
                'order_id' => $request->order_id ?? 'N/A',
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create payment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * معالجة callback من PayTabs
     */
    /**
     * Handle PayTabs callback
     *
     * @param Request $request Callback data from PayTabs
     * @return JsonResponse Callback response
     */
    public function callback(Request $request)
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

            // Get package ID from request
            $packageId = $request->get('package_id');
            $amount = $request->get('amount');

            if (!$packageId || !$amount) {
                throw new \RuntimeException('Missing package_id or amount');
            }

            // Get user from session
            $user = auth()->user();
            if (!$user) {
                throw new \RuntimeException('User not authenticated');
            }

            // Verify payment status
            if ($request->get('status') !== 'success') {
                throw new \RuntimeException('Payment not successful');
            }

            // Renew subscription
            $subscriptionService = app(\App\Services\SubscriptionService::class);
            $subscription = $subscriptionService->renewSubscription($user, $packageId, $amount);

            if (!$subscription) {
                throw new \RuntimeException('Failed to renew subscription');
            }

            // Log successful renewal
            Log::info('Subscription renewed successfully', [
                'subscription_id' => $subscription->id,
                'user_id' => $user->id,
                'package_id' => $packageId,
                'amount' => $amount,
                'environment' => app()->environment()
            ]);
            
            // تسجيل كل البيانات المستلمة للتشخيص
            Log::debug('Full callback data', [
                'all_data' => $request->all(),
                'headers' => $request->header(),
                'method' => $request->method(),
            ]);
            
            // Validate callback
            // التحقق من صحة الـ callback باستخدام التوقيع الرقمي
            if (!$this->payTabsService->validateCallback($request->getContent(), $request->header('Signature'))) {
                Log::error('Invalid callback received', $request->all());
                return response()->json(['message' => 'Invalid callback'], 400);
            }

            // الحصول على معرف الطلب
            $cartId = $request->cart_id ?? $request->cartId ?? null;
            if (!$cartId) {
                Log::error('Cart ID not found in callback data', $request->all());
                return response()->json(['message' => 'Cart ID not found'], 400);
            }

            // Verify payment status
            $verification = $this->payTabsService->verifyPayment($request->tran_ref);

            if ($verification['success'] && in_array($verification['status'], ['captured', 'Completed', 'A'])) {
                // Update payment status in database
                $payment = Payment::where('order_id', $cartId)->first();
                if ($payment) {
                    // تجنب معالجة نفس الدفع مرتين
                    if ($payment->status === 'completed') {
                        Log::info('Payment already processed', [
                            'order_id' => $cartId,
                            'transaction_id' => $request->tran_ref
                        ]);
                        
                        // إعادة توجيه تلقائي إلى صفحة تنشيط الدفع ثم العودة للصفحة الرئيسية
                        $activationUrl = route('paytabs.activate', ['transaction_ref' => $request->tran_ref]);
                        $redirectHtml = '<html><head><meta http-equiv="refresh" content="0;url=' . $activationUrl . '"></head><body><script>window.location.href="' . $activationUrl . '";</script><p>جاري التوجيه...</p></body></html>';
                        
                        return response($redirectHtml, 200)
                            ->header('Content-Type', 'text/html')
                            ->header('X-Success', 'true')
                            ->header('X-Redirect-Url', $activationUrl);
                    }

                    $payment->update([
                        'status' => 'completed',
                        'transaction_id' => $request->tran_ref,
                        'amount' => $request->tran_amount ?? $payment->amount,
                        'currency' => $request->tran_currency ?? $payment->currency,
                        'payment_method' => $request->tran_method ?? 'online'
                    ]);

                    // Log successful payment
                    Log::info('Payment completed', [
                        'order_id' => $cartId,
                        'amount' => $request->tran_amount ?? $payment->amount,
                        'currency' => $request->tran_currency ?? $payment->currency
                    ]);

                    // Create subscription if payment is for a package
                    if ($payment->package_id) {
                        $subscription = $this->createSubscriptionFromPayment($payment);
                        Log::info('Subscription creation result', [
                            'success' => $subscription ? true : false,
                            'subscription_id' => $subscription ? $subscription->id : null
                        ]);

                        if ($subscription) {
                            // تفعيل المستخدم فقط - أعضاء الفريق يظلون نشطين مستقلين
                            if ($payment->user) {
                                $payment->user->update(['is_active' => true]);
                                
                                Log::info('User activated - team members remain active independently', [
                                    'user_id' => $payment->user->id
                                ]);
                            }
                        }
                    }

                    // Send email notification
                    // Mail::to($payment->customer_email)->send(new PaymentCompleted($payment));

                    // إرجاع استجابة نجاح مع رابط إعادة التوجيه وكود HTML للتوجيه التلقائي
                    $activationUrl = route('paytabs.activate', ['transaction_ref' => $request->tran_ref]);
                    $redirectHtml = '<html><head><meta http-equiv="refresh" content="0;url=' . $activationUrl . '"></head><body><script>window.location.href="' . $activationUrl . '";</script><p>جاري التوجيه...</p></body></html>';
                    
                    return response($redirectHtml, 200)
                        ->header('Content-Type', 'text/html')
                        ->header('X-Success', 'true')
                        ->header('X-Redirect-Url', $activationUrl);
                } else {
                    Log::error('Payment record not found for order', [
                        'cart_id' => $cartId
                    ]);
                }
            }

            Log::warning('Payment verification failed', [
                'order_id' => $request->cart_id,
                'status' => $verification['status'] ?? 'unknown'
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Payment verification failed'
            ], 400);

        } catch (\Exception $e) {
            Log::error('Callback processing failed', [
                'error' => $e->getMessage(),
                'order_id' => $request->cart_id ?? 'N/A',
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Callback processing failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * التحقق من حالة المعاملة
     */
    public function checkPaymentStatus(Request $request): JsonResponse
    {
        $request->validate([
            'transaction_ref' => 'required|string'
        ]);

        try {
            $status = $this->payTabsService->checkPaymentStatus($request->transaction_ref);

            return response()->json([
                'success' => true,
                'status' => $status['status'],
                'transaction_details' => $status['details']
            ]);

        } catch (\Exception $e) {
            Log::error('PayTabs status check failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Status check failed'
            ], 500);
        }
    }

    /**
     * صفحة نجاح الدفع
     */
    public function success(Request $request)
    {
        $transactionRef = $request->get('tranRef');
        
        // تسجيل كل البيانات المستلمة للتشخيص
        Log::debug('Payment success page accessed', [
            'transaction_ref' => $transactionRef,
            'all_data' => $request->all(),
            'headers' => $request->header(),
            'method' => $request->method(),
            'url' => $request->fullUrl(),
        ]);
        
        if ($transactionRef) {
            try {
                // التحقق من حالة الدفع
                $verification = $this->payTabsService->verifyPayment($transactionRef);
                
                if ($verification['success']) {
                    // البحث عن الدفع المرتبط بهذه المعاملة
                    $cartId = $verification['raw_response']['cart_id'] ?? '';
                    $payment = Payment::where('order_id', $cartId)->first();
                    
                    if ($payment) {
                        // تجنب معالجة نفس الدفع مرتين
                        if ($payment->status === 'completed') {
                            Log::info('Payment already processed in success page', [
                                'order_id' => $cartId,
                                'transaction_id' => $transactionRef
                            ]);
                            
                            // التحقق من وجود اشتراك
                            if ($payment->subscription()->exists()) {
                                // إعادة توجيه تلقائي إلى صفحة تنشيط الدفع ثم العودة للصفحة الرئيسية
                                return redirect()->route('paytabs.activate', ['transaction_ref' => $transactionRef]);
                            } else {
                                // محاولة إنشاء الاشتراك إذا لم يكن موجوداً
                                $subscription = $this->createSubscriptionFromPayment($payment);
                                if ($subscription) {
                                    return redirect()->route('paytabs.activate', ['transaction_ref' => $transactionRef]);
                                }
                            }
                        } else {
                            // تحديث حالة الدفع إلى مكتمل
                            $payment->update([
                                'status' => 'completed',
                                'transaction_id' => $transactionRef,
                                'notes' => ($payment->notes ? $payment->notes . PHP_EOL : '') . 'تم تأكيد الدفع من صفحة النجاح'
                            ]);
                            
                            // إنشاء الاشتراك
                            $subscription = $this->createSubscriptionFromPayment($payment);
                            
                            if ($subscription) {
                                // تفعيل المستخدم فقط - أعضاء الفريق يظلون نشطين
                                if ($payment->user) {
                                    $payment->user->update(['is_active' => true]);
                                }
                                
                                return redirect()->route('paytabs.activate', ['transaction_ref' => $transactionRef]);
                            }
                        }
                    }
                }
                
                // في حالة عدم وجود دفع أو فشل إنشاء الاشتراك
                return redirect('/app')->with('warning', 'تم استلام الدفع ولكن يرجى الاتصال بالدعم لتفعيل الاشتراك');
            } catch (\Exception $e) {
                Log::error('Error processing successful payment', [
                    'transaction_ref' => $transactionRef,
                    'error' => $e->getMessage()
                ]);
                
                return redirect('/app')->with('error', 'حدث خطأ أثناء معالجة الدفع. يرجى الاتصال بالدعم.');
            }
        }

        return redirect('/')->with('error', 'مرجع الدفع غير صالح');
    }
    
    /**
     * صفحة تنشيط الدفع والعودة للصفحة الرئيسية
     */
    public function activate(Request $request, $transaction_ref = null)
    {
        // دعم كلٍ من مسار /activate/{transaction_ref} أو باراميتر ?transaction_ref=
        $transactionRef = $transaction_ref ?? $request->get('transaction_ref');
        
        // تسجيل الوصول إلى صفحة التنشيط
        Log::info('Payment activation page accessed', [
            'transaction_ref' => $transactionRef,
            'ip' => $request->ip(),
        ]);
        
        if (!$transactionRef) {
            return redirect('/app')->with('error', 'مرجع الدفع غير صالح');
        }
        
        // التحقق من وجود دفع مرتبط بهذا المرجع
        $payment = Payment::where('transaction_id', $transactionRef)->first();
        if (!$payment) {
            // دعم التفعيل باستخدام رقم الطلب بدل مرجع المعاملة
            $payment = Payment::where('order_id', $transactionRef)->first();
        }
        
        if ($payment) {
            // إذا لم يكن مكتملًا، حدّث الحالة
            if ($payment->status !== 'completed') {
                $payment->update([
                    'status' => 'completed',
                    'notes' => ($payment->notes ? $payment->notes . PHP_EOL : '') . 'تم تأكيد الدفع من صفحة التنشيط'
                ]);
            }

            // أنشئ الاشتراك إن لم يكن موجودًا
            if (!$payment->subscription()->exists() && $payment->package_id) {
                $this->createSubscriptionFromPayment($payment);
            }
        }
        
        // لا حاجة لعرض مخصص، أعد توجيه المستخدم للوحة التحكم مع رسالة نجاح
        return redirect('/admin')->with('success', 'تم تأكيد الدفع بنجاح');
    }

    /**
     * Process package payment
     *
     * @param Request $request Package payment request
     * @return \Illuminate\Http\RedirectResponse Payment redirect response
     */
    public function pay(Request $request)
    {
        $request->validate([
            'package_id' => 'required|exists:packages,id',
        ]);

        $package = Package::findOrFail($request->package_id);
        $user = auth()->user();

        if (!$user) {
            return redirect()->route('login')->with('error', 'يرجى تسجيل الدخول للاشتراك');
        }

        if ($user->hasActiveSubscription()) {
            return redirect()->back()->with('error', 'لديك اشتراك نشط بالفعل');
        }

        // Create payment record
        $payment = Payment::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'order_id' => 'ORDER-' . now()->timestamp,
            'amount' => $package->price,
            'currency' => 'SAR',
            'customer_name' => $user->name,
            'customer_email' => $user->email,
            'customer_phone' => $user->phone ?? null,
            'description' => "اشتراك في الباقة: {$package->name}",
            'notes' => "تم بدء الاشتراك من ويدجت الباقات",
            'status' => 'pending',
        ]);

        // Create PayTabs payment
        $paymentData = [
            'amount' => $package->price,
            'currency' => 'SAR',
            'customer_name' => $user->name,
            'customer_email' => $user->email,
            'customer_phone' => $user->phone ?? null,
            'order_id' => $payment->order_id,
            'description' => $payment->description,
        ];

        try {
            $response = $this->payTabsService->createPaymentPage($paymentData);

            if ($response['success']) {
                return redirect()->away($response['payment_url']);
            }

            throw new \RuntimeException($response['message'] ?? 'Failed to create payment');

        } catch (\Exception $e) {
            Log::error('Payment creation failed', [
                'error' => $e->getMessage(),
                'order_id' => $payment->order_id
            ]);

            $payment->update([
                'status' => 'failed',
                'notes' => $payment->notes . PHP_EOL . $e->getMessage()
            ]);

            return redirect()->back()->with('error', 'فشل في معالجة الدفع: ' . $e->getMessage());
        }
    }

    /**
     * صفحة فشل الدفع
     */
    public function failed(Request $request)
    {
        $transactionRef = $request->get('tranRef');
        $reason = $request->get('reason', 'فشل في الدفع');
        
        return view('payment.failed', compact('transactionRef', 'reason'));
    }

    /**
     * إلغاء المعاملة
     */
    public function cancel(Request $request)
    {
        $transactionRef = $request->get('tranRef');
        
        return view('payment.cancelled', compact('transactionRef'));
    }

    /**
     * معالجة الدفع يدوياً في البيئة المحلية
     * هذه الدالة مفيدة عندما لا يمكن لبوابة الدفع الوصول إلى البيئة المحلية
     *
     * @param string $orderId رقم الطلب
     * @return \Illuminate\Http\RedirectResponse
     */
    public function processLocalPayment($orderId)
    {
        try {
            // البحث عن الدفع بواسطة رقم الطلب
            $payment = Payment::where('order_id', $orderId)->first();
            
            if (!$payment) {
                return redirect('/app')->with('error', 'لم يتم العثور على عملية الدفع');
            }
            
            // تحديث حالة الدفع إلى مكتمل
            $payment->update([
                'status' => 'completed',
                'transaction_id' => 'LOCAL-' . now()->timestamp,
                'notes' => ($payment->notes ? $payment->notes . PHP_EOL : '') . 'تمت معالجة الدفع يدوياً في البيئة المحلية'
            ]);
            
            // إنشاء الاشتراك
            $subscription = $this->createSubscriptionFromPayment($payment);
            
            if ($subscription) {
                return redirect('/app')->with('success', 'تم إنشاء الاشتراك بنجاح');
            } else {
                return redirect('/app')->with('warning', 'تم تحديث حالة الدفع ولكن فشل إنشاء الاشتراك');
            }
            
        } catch (\Exception $e) {
            return redirect('/')->with('error', 'حدث خطأ أثناء معالجة الدفع: ' . $e->getMessage());
        }
    }

    /**
     * إنشاء اشتراك جديد من عملية دفع ناجحة
     *
     * @param Payment $payment
     * @return Subscription|null
     */
    protected function createSubscriptionFromPayment(Payment $payment)
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

            // تعيين تاريخ البداية - العضو يظل نشط حتى مع انتهاء الاشتراك
            $startDate = now();

            // إنشاء الاشتراك - انتهاء الاشتراك لا يؤثر على أعضاء الفريق
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
                'end_date' => $startDate->copy()->addYear(), // اشتراك لمدة عام - انتهاء الاشتراك لا يؤثر على أعضاء الفريق
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
}