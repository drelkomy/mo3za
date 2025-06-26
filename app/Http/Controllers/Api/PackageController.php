<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SubscribePackageRequest;
use App\Http\Resources\PackageResource;
use App\Http\Resources\Api\SubscriptionCreateResource;
use App\Models\Package;
use App\Models\Subscription;
use App\Services\SubscriptionService;
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
        $packages = Cache::remember('paid_packages_v2', 900, function () {
            return Package::where('is_active', true)
                ->where('is_trial', false)
                ->select(['id', 'name', 'price', 'max_tasks', 'max_milestones_per_task'])
                ->orderBy('price')
                ->get();
        });
        
        return response()->json([
            'message' => 'تم جلب الباقات بنجاح',
            'data' => PackageResource::collection($packages)
        ])->setMaxAge(900)->setPublic();
    }
    
    public function trial(): JsonResponse
    {
        $userId = auth()->id();
        $cacheKey = "trial_status_{$userId}";
        
        $data = Cache::remember($cacheKey, 300, function () use ($userId) {
            $hasUsedTrial = DB::table('subscriptions')
                ->join('packages', 'subscriptions.package_id', '=', 'packages.id')
                ->where('subscriptions.user_id', $userId)
                ->where('packages.is_trial', true)
                ->exists();
                
            $trialPackage = null;
            if (!$hasUsedTrial) {
                $trialPackage = Package::where('is_active', true)
                    ->where('is_trial', true)
                    ->select(['id', 'name', 'price', 'max_tasks', 'max_milestones_per_task'])
                    ->first();
            }
            
            return [
                'has_used_trial' => $hasUsedTrial,
                'trial_package' => $trialPackage
            ];
        });
        
        if ($data['has_used_trial']) {
            return response()->json([
                'message' => 'لقد استخدمت الباقة التجريبية من قبل',
                'trial_package' => null,
                'can_renew' => false
            ], 422);
        }
        
        return response()->json([
            'message' => 'الباقة التجريبية متاحة',
            'trial_package' => $data['trial_package'] ? new PackageResource($data['trial_package']) : null
        ])->setMaxAge(300)->setPublic();
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
            
            // تنظيف الكاش
            $this->clearUserCache($userId);
            
            // تنظيف كاش الفريق إذا كان المستخدم لديه فريق
            $team = \App\Models\Team::where('owner_id', $userId)->first();
            if ($team) {
                $this->clearTeamCache($team->id);
            }
            
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
    
    private function clearUserCache(int $userId): void
    {
        $cacheKeys = [
            "user_subscription_{$userId}",
            "trial_status_{$userId}",
            "current_subscription_{$userId}",
            "user_{$userId}_has_active_subscription"
        ];
        
        foreach ($cacheKeys as $key) {
            Cache::forget($key);
        }
    }
    
    /**
     * مسح كاش الفريق
     */
    private function clearTeamCache(int $teamId): void
    {
        // مسح كاش المهام
        for ($i = 1; $i <= 5; $i++) {
            Cache::forget("team_tasks_{$teamId}_page_{$i}_per_10_status_");
            Cache::forget("team_tasks_{$teamId}_page_{$i}_per_10_status_completed");
            Cache::forget("team_tasks_{$teamId}_page_{$i}_per_10_status_pending");
            Cache::forget("team_tasks_{$teamId}_page_{$i}_per_10_status_in_progress");
            
            // مسح كاش الإحصائيات
            Cache::forget("team_members_task_stats_{$teamId}_page_{$i}_per_10");
            Cache::forget("team_members_task_stats_{$teamId}_page_{$i}_tasks_per_page_10");
        }
    }
}