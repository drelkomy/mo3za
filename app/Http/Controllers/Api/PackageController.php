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
        
        $packages = Cache::remember($cacheKey . '_' . auth()->id(), 600, function () {
            $query = Package::where('is_active', true)
                ->select(['id', 'name', 'price', 'max_tasks', 'max_milestones_per_task', 'is_trial']);
            
            // إخفاء الباقة التجريبية إذا استخدمها المستخدم من قبل
            $hasUsedTrial = auth()->user()->subscriptions()
                ->whereHas('package', function($q) {
                    $q->where('is_trial', true);
                })->exists();
                
            if ($hasUsedTrial) {
                $query->where('is_trial', false);
            }
            
            return $query->orderBy('price')->get();
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
            ->first();
            
        if ($activeSubscription) {
            return response()->json([
                'message' => 'لديك اشتراك نشط بالفعل',
                'current_subscription' => [
                    'package_name' => $activeSubscription->package->name,
                    'status' => $activeSubscription->status
                ]
            ], 422);
        }
        
        // إنشاء الاشتراك
        $subscription = Subscription::create([
            'user_id' => auth()->id(),
            'package_id' => $package->id,
            'start_date' => now(),
            'status' => $package->is_trial ? 'active' : 'pending',
            'price_paid' => $package->price,
        ]);
        
        // تنظيف cache
        Cache::forget('user_subscription_' . auth()->id());
        
        RateLimiter::hit($key, 300);
        
        $response = [
            'message' => $package->is_trial ? 'تم تفعيل الباقة التجريبية بنجاح' : 'تم إنشاء طلب الاشتراك بنجاح',
            'subscription_id' => $subscription->id,
            'status' => $subscription->status
        ];
        
        // إضافة رابط الدفع للباقات الحقيقية (غير التجريبية)
        if (!$package->is_trial && $package->price > 0) {
            $paymentUrl = $this->generatePaymentUrl($subscription, $package);
            $response['payment_url'] = $paymentUrl;
            $response['payment_instructions'] = 'يرجى إكمال عملية الدفع لتفعيل الاشتراك';
        }
        
        return response()->json($response, 201);
    }
    
    private function generatePaymentUrl($subscription, $package): string
    {
        // PayTabs API Integration
        $paymentData = [
            'profile_id' => config('paytabs.profile_id'),
            'tran_type' => 'sale',
            'tran_class' => 'ecom',
            'cart_id' => 'subscription_' . $subscription->id,
            'cart_description' => 'اشتراك في باقة: ' . $package->name,
            'cart_currency' => 'SAR',
            'cart_amount' => $package->price,
            'callback' => url('/api/payment/callback'),
            'return' => url('/api/payment/success/' . $subscription->id),
            'customer_details' => [
                'name' => auth()->user()->name,
                'email' => auth()->user()->email,
                'phone' => auth()->user()->phone ?? '966500000000',
                'street1' => 'N/A',
                'city' => 'Riyadh',
                'state' => 'Riyadh',
                'country' => 'SA',
                'zip' => '12345'
            ]
        ];
        
        // إرسال طلب لPayTabs لإنشاء رابط الدفع
        $response = \Illuminate\Support\Facades\Http::withHeaders([
            'Authorization' => config('paytabs.server_key'),
            'Content-Type' => 'application/json'
        ])->post('https://secure.paytabs.sa/payment/request', $paymentData);
        
        if ($response->successful()) {
            return $response->json('redirect_url');
        }
        
        // Fallback URL في حالة فشل الطلب
        return url('/payment/error');
    }
}