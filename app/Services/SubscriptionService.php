<?php

namespace App\Services;

use App\Models\Package;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SubscriptionService
{
    public function createTrialSubscription(User $user): ?Subscription
    {
        $trialPackage = Package::where('name', 'الباقة التجريبية')->first();
        
        if (!$trialPackage || $user->trial_used) {
            return null;
        }

        $user->update(['trial_used' => true]);

        return Subscription::create([
            'user_id' => $user->id,
            'package_id' => $trialPackage->id,
            'status' => 'active',
            'price_paid' => 0,
            'max_tasks' => $trialPackage->max_tasks,
            'max_milestones_per_task' => $trialPackage->max_milestones_per_task,
            'tasks_created' => 0,
            'previous_tasks_completed' => 0,
            'previous_tasks_pending' => 0,
            'previous_rewards_amount' => 0,
        ]);
    }

    /**
     * Renew a subscription after successful payment
     */
    public function renewSubscription(User $user, string $packageId, float $amountPaid): ?Subscription
    {
        $package = Package::find($packageId);
        
        if (!$package) {
            return null;
        }

        // Get current subscription to update previous stats
        $currentSubscription = $user->activeSubscription;
        
        return Subscription::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'status' => 'active',
            'price_paid' => $amountPaid,
            'max_tasks' => $package->max_tasks,
            'max_milestones_per_task' => $package->max_milestones_per_task,
            'tasks_created' => 0,
            'previous_tasks_completed' => $currentSubscription?->previous_tasks_completed ?? 0,
            'previous_tasks_pending' => $currentSubscription?->previous_tasks_pending ?? 0,
            'previous_rewards_amount' => $currentSubscription?->previous_rewards_amount ?? 0,
        ]);
    }
    
    /**
     * إنشاء اشتراك جديد (تجريبي أو مدفوع)
     */
    public function createSubscription(int $userId, int $packageId): Subscription
    {
        // جلب الباقة
        $package = Package::findOrFail($packageId);
        if (!$package->is_active) {
            throw new \Exception('الباقة غير متاحة للاشتراك');
        }
        
        // جلب المستخدم
        $user = User::findOrFail($userId);
        
        // التحقق من الباقة التجريبية
        if ($package->is_trial) {
            // التحقق من استخدام الباقة التجريبية سابقاً
            $hasUsedTrial = Subscription::where('user_id', $userId)
                ->whereHas('package', function($q) {
                    $q->where('is_trial', true);
                })->exists();
                
            if ($hasUsedTrial) {
                throw new \Exception('لا يمكن الاشتراك في الباقة التجريبية أكثر من مرة واحدة');
            }
            
            // تحديث حالة المستخدم
            $user->update(['trial_used' => true]);
        }
        
        // التحقق من وجود اشتراك نشط
        $existingSubscription = Subscription::where('user_id', $userId)
            ->whereIn('status', ['active', 'pending'])
            ->first();
            
        if ($existingSubscription) {
            throw new \Exception('لديك اشتراك نشط بالفعل');
        }
        
        // إنشاء الاشتراك
        $subscription = Subscription::create([
            'user_id' => $userId,
            'package_id' => $package->id,
            'start_date' => now(),
            'status' => $package->is_trial ? 'active' : 'pending',
            'price_paid' => $package->price,
            'max_tasks' => $package->max_tasks,
            'max_milestones_per_task' => $package->max_milestones_per_task,
            'tasks_created' => 0,
        ]);
        
        // إضافة معلومات الدفع للاشتراكات المدفوعة
        if (!$package->is_trial && $package->price > 0) {
            $subscription->payment_url = $this->generatePaymentUrl($subscription, $package);
        }
        
        return $subscription;
    }

    /**
     * إنشاء رابط الدفع للاشتراك
     */
    private function generatePaymentUrl(Subscription $subscription, Package $package): string
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
                'name' => User::find($subscription->user_id)->name,
                'email' => User::find($subscription->user_id)->email,
                'phone' => User::find($subscription->user_id)->phone ?? '966500000000',
                'street1' => 'N/A',
                'city' => 'Riyadh',
                'state' => 'Riyadh',
                'country' => 'SA',
                'zip' => '12345'
            ]
        ];
        
        try {
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'Authorization' => config('paytabs.server_key'),
                'Content-Type' => 'application/json'
            ])->post('https://secure.paytabs.sa/payment/request', $paymentData);
            
            if ($response->successful()) {
                return $response->json('redirect_url');
            }
        } catch (\Exception $e) {
            Log::error('Payment URL generation failed', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage()
            ]);
        }
        
        return url('/payment/error');
    }

 

    public function canAddTeamMembers(User $user): bool
    {
        $subscription = $user->activeSubscription;
        return $subscription && !($subscription->isExpired());
    }

    public function incrementTasksCreated(User $user): bool
    {
        $subscription = $user->activeSubscription;
        if (!$subscription) {
            Log::error('No active subscription found for user', ['user_id' => $user->id]);
            return false;
        }
        
        if ($user->canAddTasks()) {
            // استخدام معاملة قاعدة البيانات لضمان تحديث البيانات بشكل آمن
            DB::beginTransaction();
            try {
                // زيادة عدد المهام المنشأة
                $subscription->increment('tasks_created');
                
                // تحديث النموذج للحصول على القيمة المحدثة
                $subscription->refresh();
                
                // التحقق من انتهاء الباقة عند الوصول للحد الأقصى
                if ($subscription->tasks_created >= $subscription->max_tasks) {
                    // تحديث حالة الاشتراك إلى منتهي
                    $subscription->update(['status' => 'expired']);
                    
                    // تسجيل انتهاء الاشتراك
                    Log::info('Subscription expired due to tasks limit. User should be redirected to renewal page.', [
                        'subscription_id' => $subscription->id,
                        'user_id' => $subscription->user_id,
                        'tasks_created' => $subscription->tasks_created,
                        'max_tasks' => $subscription->max_tasks
                    ]);
                }
                
                // مسح الكاش المتعلق بالاشتراك
                Cache::forget("user_{$user->id}_has_active_subscription");
                Cache::forget("user_subscription_{$user->id}");
                
                DB::commit();
                return true;
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Error incrementing tasks_created', [
                    'user_id' => $user->id,
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage()
                ]);
                return false;
            }
        }
        return false;
    }
}
