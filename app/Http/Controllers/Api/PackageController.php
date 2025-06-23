<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SubscribePackageRequest;
use App\Http\Resources\PackageResource;
use App\Models\Package;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

class PackageController extends Controller
{
    public function index(): JsonResponse
    {
        $packages = Cache::remember('paid_packages', 600, function () {
            return Package::where('is_active', true)
                ->where('is_trial', false)
                ->select(['id', 'name', 'price', 'max_tasks', 'max_milestones_per_task', 'is_trial'])
                ->orderBy('price')
                ->get();
        });
        
        return response()->json([
            'message' => 'تم جلب الباقات بنجاح',
            'data' => PackageResource::collection($packages)
        ])->setMaxAge(600)->setPublic();
    }
    
    public function trial(): JsonResponse
    {
        // فحص إذا استخدم المستخدم الباقة التجريبية من قبل
        $hasUsedTrial = auth()->user()->subscriptions()
            ->whereHas('package', function($q) {
                $q->where('is_trial', true);
            })->exists();
            
        if ($hasUsedTrial) {
            return response()->json([
                'message' => 'لقد استخدمت الباقة التجريبية من قبل - لا يمكن تجديدها',
                'trial_package' => null,
                'can_renew' => false
            ], 422);
        }
        
        $trialPackage = Package::where('is_active', true)
            ->where('is_trial', true)
            ->select(['id', 'name', 'price', 'max_tasks', 'max_milestones_per_task', 'is_trial'])
            ->first();
            
        return response()->json([
            'message' => 'الباقة التجريبية متاحة',
            'trial_package' => $trialPackage ? new PackageResource($trialPackage) : null
        ]);
    }
    
    public function subscribe(SubscribePackageRequest $request): JsonResponse
    {
        $key = 'subscribe:' . auth()->id();
        if (RateLimiter::tooManyAttempts($key, 3)) {
            return response()->json(['message' => 'عدد كبير من محاولات الاشتراك. يرجى المحاولة لاحقاً.'], 429);
        }
        
        $package = Package::find($request->input('package_id'));
        
        // منع تجديد الباقة التجريبية
        if ($package->is_trial) {
            $hasUsedTrial = auth()->user()->subscriptions()
                ->whereHas('package', function($q) {
                    $q->where('is_trial', true);
                })->exists();
                
            if ($hasUsedTrial) {
                return response()->json([
                    'message' => 'لا يمكن الاشتراك في الباقة التجريبية أكثر من مرة واحدة'
                ], 422);
            }
        }
        
        // فحص وجود اشتراك نشط أو معلق
        $existingSubscription = auth()->user()->subscriptions()
            ->whereIn('status', ['active', 'pending'])
            ->first();
            
        if ($existingSubscription) {
            return response()->json([
                'message' => 'لديك اشتراك بالفعل',
                'current_subscription' => [
                    'package_name' => $existingSubscription->package->name,
                    'status' => $existingSubscription->status
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
        
        // إضافة رابط الدفع للباقات المدفوعة
        if (!$package->is_trial && $package->price > 0) {
            $paymentUrl = $this->generatePaymentUrl($subscription, $package);
            $response['payment_url'] = $paymentUrl;
            $response['payment_instructions'] = 'يرجى إكمال عملية الدفع لتفعيل الاشتراك';
        }
        
        return response()->json($response, 201);
    }
    
    private function generatePaymentUrl($subscription, $package): string
    {
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
        
        $response = \Illuminate\Support\Facades\Http::withHeaders([
            'Authorization' => config('paytabs.server_key'),
            'Content-Type' => 'application/json'
        ])->post('https://secure.paytabs.sa/payment/request', $paymentData);
        
        if ($response->successful()) {
            return $response->json('redirect_url');
        }
        
        return url('/payment/error');
    }
}