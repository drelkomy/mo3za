<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SubscribePackageRequest;
use App\Http\Resources\PackageResource;
use App\Http\Resources\Api\SubscriptionCreateResource;
use App\Models\Package;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use App\Services\CacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\DB;

class PackageController extends Controller
{
    public function __construct(
        private SubscriptionService $subscriptionService
    ) {}

    public function index(): JsonResponse
    {
        $packages = Package::where('is_active', true)
            ->where('is_trial', false)
            ->select(['id', 'name', 'price', 'max_tasks', 'max_milestones_per_task'])
            ->orderBy('price')
            ->get();
        
        return response()->json([
            'message' => 'تم جلب الباقات بنجاح',
            'data' => PackageResource::collection($packages)
        ]);
    }
    
    public function trial(): JsonResponse
    {
        $userId = auth()->id();
        
        $hasUsedTrial = DB::table('subscriptions')
            ->join('packages', 'subscriptions.package_id', '=', 'packages.id')
            ->where('subscriptions.user_id', $userId)
            ->where('packages.is_trial', true)
            ->exists();
            
        if ($hasUsedTrial) {
            return response()->json([
                'message' => 'لقد استخدمت الباقة التجريبية من قبل',
                'trial_package' => null,
                'can_renew' => false
            ], 422);
        }
        
        $trialPackage = Package::where('is_active', true)
            ->where('is_trial', true)
            ->select(['id', 'name', 'price', 'max_tasks', 'max_milestones_per_task'])
            ->first();
        
        return response()->json([
            'message' => 'الباقة التجريبية متاحة',
            'trial_package' => $trialPackage ? new PackageResource($trialPackage) : null
        ]);
    }
    
    public function subscribe(SubscribePackageRequest $request): JsonResponse
    {
        $userId = auth()->id();
        $key = "subscribe:{$userId}";
        
        // تحديد عدد الطلبات - 2 محاولات كل 5 دقائق
        if (RateLimiter::tooManyAttempts($key, 2)) {
            return response()->json([
                'message' => 'عدد كبير من محاولات الاشتراك. يرجى المحاولة بعد 5 دقائق.',
                'error' => 'rate_limit_exceeded'
            ], 429);
        }
        
        try {
            DB::beginTransaction();
            
            // استخدام Service لتحسين الأداء
            $subscription = $this->subscriptionService->createSubscription(
                $userId,
                $request->validated()['package_id']
            );
            
            DB::commit();
            
            // تسجيل المحاولة
            RateLimiter::hit($key, 300);
            
            // لا نحتاج لتنظيف الكاش - الاشتراكات بدون كاش
            
            return response()->json([
                'message' => $subscription->package->is_trial 
                    ? 'تم تفعيل الباقة التجريبية بنجاح' 
                    : 'تم إنشاء طلب الاشتراك بنجاح',
                'data' => new SubscriptionCreateResource($subscription)
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            // تسجيل الخطأ
            \Illuminate\Support\Facades\Log::error('Subscription error: ' . $e->getMessage(), [
                'user_id' => $userId,
                'package_id' => $request->validated()['package_id'],
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'حدث خطأ أثناء الاشتراك: ' . $e->getMessage(),
                'error' => 'subscription_error'
            ], 422);
        }
    }
    

    

}