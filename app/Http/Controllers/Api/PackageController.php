<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SubscribePackageRequest;
use App\Http\Resources\PackageResource;
use App\Http\Resources\PackageCollection;
use App\Models\Package;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

class PackageController extends Controller
{
    public function index(): JsonResponse
    {
        $cacheKey = 'packages_list';
        
        $packages = Cache::remember($cacheKey, 600, function () {
            return Package::where('is_active', true)
                ->select(['id', 'name', 'description', 'price', 'duration_days', 'max_tasks', 'max_team_members', 'features', 'is_trial'])
                ->orderBy('price')
                ->get();
        });
        
        return response()->json([
            'message' => 'تم جلب الباقات بنجاح',
            'data' => new PackageCollection($packages)
        ])->setMaxAge(600)->setPublic();
    }
    
    public function subscribe(SubscribePackageRequest $request): JsonResponse
    {
        $key = 'subscribe:' . auth()->id();
        if (RateLimiter::tooManyAttempts($key, 3)) {
            return response()->json(['message' => 'عدد كبير من محاولات الاشتراك. يرجى المحاولة لاحقاً.'], 429);
        }
        
        $package = Package::find($request->input('package_id'));
        
        // فحص الاشتراك النشط
        $activeSubscription = auth()->user()->subscriptions()
            ->where('status', 'active')
            ->where('end_date', '>', now())
            ->first();
            
        if ($activeSubscription) {
            return response()->json([
                'message' => 'لديك اشتراك نشط بالفعل',
                'current_subscription' => [
                    'package_name' => $activeSubscription->package->name,
                    'end_date' => $activeSubscription->end_date
                ]
            ], 422);
        }
        
        // إنشاء الاشتراك
        $subscription = Subscription::create([
            'user_id' => auth()->id(),
            'package_id' => $package->id,
            'start_date' => now(),
            'end_date' => now()->addDays($package->duration_days),
            'status' => $package->is_trial ? 'active' : 'pending',
            'amount' => $package->price,
        ]);
        
        // تنظيف cache
        Cache::forget('user_subscription_' . auth()->id());
        
        RateLimiter::hit($key, 300);
        
        $response = [
            'message' => $package->is_trial ? 'تم تفعيل الباقة التجريبية بنجاح' : 'تم إنشاء طلب الاشتراك بنجاح',
            'subscription_id' => $subscription->id,
            'status' => $subscription->status
        ];
        
        // إضافة رابط الدفع للباقات المدفوعة
        if (!$package->is_trial) {
            $paymentUrl = $this->generatePaymentUrl($subscription, $package);
            $response['payment_url'] = $paymentUrl;
            $response['payment_instructions'] = 'يرجى إكمال عملية الدفع لتفعيل الاشتراك';
        }
        
        return response()->json($response, 201);
    }
    
    private function generatePaymentUrl($subscription, $package): string
    {
        // مثال لبوابة دفع (PayPal, Stripe, فوري, إلخ)
        $paymentData = [
            'amount' => $package->price,
            'currency' => 'SAR',
            'description' => 'اشتراك في باقة: ' . $package->name,
            'subscription_id' => $subscription->id,
            'success_url' => url('/api/payment/success/' . $subscription->id),
            'cancel_url' => url('/api/payment/cancel/' . $subscription->id),
            'webhook_url' => url('/api/payment/callback'),
        ];
        
        // هنا تضع كود بوابة الدفع الخاصة بك
        // مثال لفوري:
        return 'https://api.moyasar.com/v1/payments?' . http_build_query($paymentData);
        
        // أو مثال لPayPal:
        // return 'https://www.paypal.com/cgi-bin/webscr?' . http_build_query($paymentData);
    }
}