<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * تحويل المورد إلى مصفوفة.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // الحصول على الاشتراك النشط
        $activeSubscription = $this->subscriptions()->where('status', 'active')->first();
        $createdTasksCount = $this->createdTasks()->count();
        
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'user_type' => $this->user_type,
            'is_active' => $this->is_active,
          
            // معلومات الاشتراك والباقة
            'subscription' => $activeSubscription ? [
                'id' => $activeSubscription->id,
                'package_name' => $activeSubscription->package->name,
                'package_type' => $activeSubscription->package->is_trial ? 'تجريبية' : 'مدفوعة',
                'status' => $activeSubscription->status,
                'max_tasks' => $activeSubscription->package->max_tasks,
                'created_tasks_count' => $createdTasksCount,
                'remaining_tasks' => max(0, $activeSubscription->package->max_tasks - $createdTasksCount),
                'is_exhausted' => $createdTasksCount >= $activeSubscription->package->max_tasks,
            ] : null,
            
            'has_active_subscription' => $activeSubscription !== null,
            
            // العلاقات
            'owned_teams' => $this->whenLoaded('ownedTeams', function () {
                return $this->ownedTeams;
            }),
            'teams' => $this->whenLoaded('teams', function () {
                return $this->teams;
            }),
        ];
    }
}