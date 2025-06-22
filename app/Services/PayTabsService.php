<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class PayTabsService
{
    protected $apiUrl;
    protected $serverKey;
    protected $profileId;
    protected $clientKey;
    protected $environment;

    /**
     * Create a new PayTabs service instance
     */
    public function __construct()
    {
        $this->apiUrl = config('paytabs.base_url', 'https://secure.paytabs.sa');
        $this->serverKey = config('paytabs.server_key');
        $this->profileId = config('paytabs.profile_id');
        $this->clientKey = config('paytabs.client_key');
        $this->environment = config('paytabs.environment');

        // Ensure required configurations are set
        if (!$this->serverKey || !$this->profileId) {
            throw new \RuntimeException('PayTabs configuration missing. Please check your .env file.');
        }
    }

    /**
     * Create a new payment transaction
     *
     * @param array $data Payment data including amount, currency, customer details, etc.
     * @return array Payment response with success status and payment URL
     * @throws \RuntimeException If payment creation fails
     */
    public function createPayment(array $data): array
    {
        try {
            $payload = [
                'profile_id' => $this->profileId,
                'tran_type' => 'sale',
                'tran_class' => 'ecom',
                'cart_id' => $data['order_id'],
                'cart_description' => $data['description'],
                'cart_currency' => $data['currency'],
                'cart_amount' => $data['amount'],
                'customer_details' => [
                    'name' => $data['customer_name'],
                    'email' => $data['customer_email'],
                    'phone' => $data['customer_phone'],
                    'street1' => 'Main Street',
                    'city' => 'City',
                    'state' => 'State',
                    'country' => 'EG',
                    'zip' => '12345'
                ],
                'callback' => route('paytabs.callback'),
                'return' => route('paytabs.success')
            ];

            $response = Http::withHeaders([
                'Authorization' => $this->serverKey,
                'Content-Type' => 'application/json'
            ])->post($this->apiUrl . '/payment/request', $payload);

            if ($response->successful()) {
                $responseData = $response->json();
                
                // حفظ بيانات المعاملة في قاعدة البيانات
                $this->savePaymentRecord([
                    'order_id' => $data['order_id'],
                    'transaction_ref' => $responseData['tran_ref'] ?? null,
                    'amount' => $data['amount'],
                    'currency' => $data['currency'],
                    'customer_email' => $data['customer_email'],
                    'status' => 'pending',
                    'created_at' => now()
                ]);

                return [
                    'success' => true,
                    'payment_url' => $responseData['redirect_url'],
                    'transaction_ref' => $responseData['tran_ref']
                ];
            }

            Log::error('PayTabs API Error: ' . $response->body());
            
            return [
                'success' => false,
                'message' => 'Payment gateway error: ' . $response->json()['message'] ?? 'Unknown error'
            ];

        } catch (\Exception $e) {
            Log::error('PayTabs Service Error: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Service error occurred'
            ];
        }
    }

    /**
     * Create a payment page
     *
     * @param array $data Payment data including amount, currency, customer details, etc.
     * @return array Payment response with success status and payment URL
     * @throws \RuntimeException If payment page creation fails
     */
    public function createPaymentPage(array $data): array
    {
        try {
            $payload = [
                'profile_id' => $this->profileId,
                'tran_type' => 'sale',
                'tran_class' => 'ecom',
                'cart_id' => $data['order_id'],
                'cart_description' => $data['description'],
                'cart_currency' => $data['currency'] ?? config('paytabs.currency', 'SAR'),
                'cart_amount' => $data['amount'],
                'callback_url' => route('paytabs.callback'),
                'return_url' => route('paytabs.success'), // استخدام صفحة النجاح للتوجيه التلقائي
                'framed' => false, // إلغاء وضع الإطار للسماح بالتوجيه التلقائي
                'error_url' => route('paytabs.failed'),
                'customer_details' => [
                    'name' => $data['customer_name'],
                    'email' => $data['customer_email'],
                    'phone' => $data['customer_phone'],
                    'street1' => 'Main Street',
                    'city' => 'City',
                    'state' => 'State',
                    'country' => 'SA',
                    'zip' => '12345'
                ],
                'shipping_details' => [
                    'name' => $data['customer_name'],
                    'email' => $data['customer_email'],
                    'phone' => $data['customer_phone'],
                    'street1' => 'Main Street',
                    'city' => 'City',
                    'state' => 'State',
                    'country' => 'SA',
                    'zip' => '12345'
                ],
                'hide_shipping' => true
            ];

            // Log the request payload for debugging
            Log::info('PayTabs payment request payload:', [
                'url' => $this->apiUrl . '/payment/request',
                'payload' => $payload
            ]);

            $response = Http::withHeaders([
                'Authorization' => $this->serverKey,
                'Content-Type' => 'application/json'
            ])->post($this->apiUrl . '/payment/request', $payload);

            // Log the response for debugging
            Log::info('PayTabs payment response:', [
                'status' => $response->status(),
                'body' => $response->json()
            ]);

            if ($response->successful()) {
                $responseData = $response->json();
                
                if (isset($responseData['redirect_url'])) {
                    return [
                        'success' => true,
                        'payment_url' => $responseData['redirect_url'],
                        'transaction_id' => $responseData['tran_ref'] ?? null,
                        'message' => 'Payment page created successfully'
                    ];
                }
            }

            Log::error('Failed to create payment page: ' . $response->body());
            throw new \RuntimeException('Failed to create payment page: ' . ($response['message'] ?? 'Unknown error'));

        } catch (\Exception $e) {
            Log::error('PayTabs Service Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Service error occurred: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Verify the status of a payment transaction
     *
     * @param string $transactionId The transaction reference ID
     * @return array Payment verification response
     * @throws \RuntimeException If verification fails
     */
    public function verifyPayment(string $transactionId): array
    {
        try {
            // التعامل مع المعاملات المحلية
            if (str_starts_with($transactionId, 'LOCAL-')) {
                return [
                    'success' => true,
                    'status' => 'captured',
                    'amount' => 0,
                    'currency' => 'SAR',
                    'message' => 'Local transaction processed successfully',
                    'raw_response' => [
                        'payment_result' => 'Completed',
                        'response_status' => 'A',
                        'cart_id' => str_replace('LOCAL-', 'ORDER-', $transactionId)
                    ]
                ];
            }
            
            // التعامل مع صفحة النجاح في البيئة المحلية
            if (app()->environment('local') && !str_contains($transactionId, 'ORDER-')) {
                // في البيئة المحلية، نفترض أن المعاملة ناجحة
                Log::info('Local success page verification, assuming success for: ' . $transactionId);
                
                // محاولة العثور على آخر دفع معلق للمستخدم الحالي
                $user = auth()->user();
                if ($user) {
                    $payment = DB::table('payments')
                        ->where('user_id', $user->id)
                        ->where('status', 'pending')
                        ->orderBy('created_at', 'desc')
                        ->first();
                    
                    if ($payment) {
                        return [
                            'success' => true,
                            'status' => 'captured',
                            'amount' => $payment->amount,
                            'currency' => $payment->currency,
                            'message' => 'Local success page verification',
                            'raw_response' => [
                                'payment_result' => 'Completed',
                                'response_status' => 'A',
                                'cart_id' => $payment->order_id
                            ]
                        ];
                    }
                }
            }
            
            $payload = [
                'profile_id' => $this->profileId,
                'tran_ref' => $transactionId
            ];
            
            // Log the request payload for debugging
            Log::info('PayTabs verification request:', [
                'url' => $this->apiUrl . '/payment/query',
                'payload' => $payload
            ]);
            
            // التحقق مما إذا كنا في بيئة محلية
            if (app()->environment('local') && config('app.debug')) {
                Log::warning('Running in local environment, payment verification might not work as expected');
            }
            
            $response = Http::withHeaders([
                'Authorization' => $this->serverKey,
                'Content-Type' => 'application/json'
            ])->post($this->apiUrl . '/payment/query', $payload);

            // Log the response for debugging
            Log::info('PayTabs verification response:', [
                'status' => $response->status(),
                'body' => $response->json()
            ]);

            if ($response->successful()) {
                $responseData = $response->json();
                
                return [
                    'success' => true,
                    'status' => $responseData['payment_result'] ?? $responseData['tran_status'] ?? 'unknown',
                    'amount' => $responseData['cart_amount'] ?? $responseData['tran_amount'] ?? 0,
                    'currency' => $responseData['cart_currency'] ?? $responseData['tran_currency'] ?? 'SAR',
                    'message' => $responseData['payment_info'] ?? $responseData['tran_status_msg'] ?? 'Transaction processed',
                    'raw_response' => $responseData
                ];
            }

            Log::error('Failed to verify payment: ' . $response->body());
            throw new \RuntimeException('Failed to verify payment: ' . ($response['message'] ?? 'Unknown error'));
        } catch (\Exception $e) {
            Log::error('Payment verification error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * التحقق من صحة callback
     */
    /**
     * التحقق من صحة callback عبر التوقيع الرقمي
     * @param string $payload The raw POST data from the callback
     * @param string|null $requestSignature The signature from the 'Signature' header
     * @return bool
     */
    public function validateCallback(string $payload, ?string $requestSignature): bool
    {
        // أولاً، التحقق من المعاملات المحلية التي قد لا تحتوي على توقيع
        $data = json_decode($payload, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($data['tran_ref']) && str_starts_with($data['tran_ref'], 'LOCAL-')) {
            Log::info('Bypassing signature validation for local transaction.');
            return true;
        }

        // إذا لم يتم توفير توقيع لمعاملة غير محلية، فهي غير صالحة
        if (empty($requestSignature)) {
            Log::warning('PayTabs callback received without signature header.');
            return false;
        }

        $serverKey = $this->serverKey;
        $computedSignature = hash_hmac('sha256', $payload, $serverKey);

        if (hash_equals($computedSignature, $requestSignature)) {
            // التوقيع صحيح
            return true;
        }

        // التوقيع غير صحيح
        Log::error('Invalid PayTabs callback signature.', [
            'received_signature' => $requestSignature,
            'computed_signature' => $computedSignature,
        ]);
        return false;
    }

    /**
     * معالجة callback
     */
    public function processCallback(array $data): array
    {
        try {
            $transactionRef = $data['tran_ref'];
            $paymentResult = $data['payment_result'];
            $responseStatus = $data['response_status'];

            // تحديث حالة المعاملة في قاعدة البيانات
            $payment = DB::table('payments')
                ->where('transaction_ref', $transactionRef)
                ->first();

            if (!$payment) {
                return [
                    'success' => false,
                    'message' => 'Payment record not found'
                ];
            }

            $status = $this->mapPaymentStatus($paymentResult, $responseStatus);
            
            DB::table('payments')
                ->where('transaction_ref', $transactionRef)
                ->update([
                    'status' => $status,
                    'payment_result' => $paymentResult,
                    'response_status' => $responseStatus,
                    'callback_data' => json_encode($data),
                    'updated_at' => now()
                ]);

            // إرسال إشعارات أو تنفيذ عمليات إضافية حسب الحالة
            if ($status === 'completed') {
                $this->handleSuccessfulPayment($payment, $data);
            } elseif ($status === 'failed') {
                $this->handleFailedPayment($payment, $data);
            }

            return [
                'success' => true,
                'status' => $status
            ];

        } catch (\Exception $e) {
            Log::error('Callback processing error: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Callback processing failed'
            ];
        }
    }

    /**
     * التحقق من حالة المعاملة
     */
    public function checkPaymentStatus(string $transactionRef): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => $this->serverKey,
                'Content-Type' => 'application/json'
            ])->post($this->apiUrl . '/payment/query', [
                'profile_id' => $this->profileId,
                'tran_ref' => $transactionRef
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                return [
                    'status' => $this->mapPaymentStatus(
                        $data['payment_result'] ?? '', 
                        $data['response_status'] ?? ''
                    ),
                    'details' => $data
                ];
            }

            return [
                'status' => 'unknown',
                'details' => []
            ];

        } catch (\Exception $e) {
            Log::error('Payment status check error: ' . $e->getMessage());
            
            return [
                'status' => 'error',
                'details' => []
            ];
        }
    }

    /**
     * حفظ سجل المعاملة
     */
    private function savePaymentRecord(array $data): void
    {
        DB::table('payments')->insert($data);
    }

    /**
     * تحويل حالة الدفع إلى حالة محلية
     */
    private function mapPaymentStatus(string $paymentResult, string $responseStatus): string
    {
        if ($paymentResult === 'Completed' && $responseStatus === 'A') {
            return 'completed';
        } elseif ($paymentResult === 'Failed' || $responseStatus === 'D') {
            return 'failed';
        } elseif ($paymentResult === 'Cancelled' || $responseStatus === 'C') {
            return 'cancelled';
        } else {
            return 'pending';
        }
    }

    /**
     * معالجة الدفع الناجح
     */
    private function handleSuccessfulPayment($payment, array $callbackData): void
    {
        // إضافة منطق معالجة الدفع الناجح
        // مثل: إرسال إيميل تأكيد، تحديث الطلب، إلخ
        Log::info('Payment completed successfully', [
            'order_id' => $payment->order_id,
            'amount' => $payment->amount
        ]);
    }

    /**
     * معالجة الدفع الفاشل
     */
    private function handleFailedPayment($payment, array $callbackData): void
    {
        // إضافة منطق معالجة الدفع الفاشل
        Log::warning('Payment failed', [
            'order_id' => $payment->order_id,
            'reason' => $callbackData['payment_result'] ?? 'Unknown'
        ]);
    }
}