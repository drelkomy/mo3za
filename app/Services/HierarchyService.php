<?php

namespace App\Services;

use App\Models\User;
use App\Models\RoleChangeRequest;
use Illuminate\Support\Facades\DB;

class HierarchyService
{
    /**
     * Check if user meets auto-approval conditions for role change.
     */
    public function meetsAutoApprovalConditions(User $user): bool
    {
        $conditions = config('hierarchy.role_change_settings.auto_approve_conditions');
        
        // عدد المهام المكتملة
        $completedTasks = $user->tasks()->where('status', 'completed')->count();
        if ($completedTasks < $conditions['min_tasks_completed']) {
            return false;
        }
        
        // عدد الأيام كمشارك
        $daysAsParticipant = $user->created_at->diffInDays(now());
        if ($daysAsParticipant < $conditions['min_days_as_participant']) {
            return false;
        }
        
        // إجمالي المكافآت
        $totalRewards = $user->rewards()->sum('amount') ?? 0;
        if ($totalRewards < $conditions['min_reward_amount']) {
            return false;
        }
        
        return true;
    }

    /**
     * Process role change request.
     */
    public function processRoleChange(RoleChangeRequest $request): bool
    {
        return DB::transaction(function () use ($request) {
            $user = $request->user;
            
            if ($request->to_role === 'داعم') {
                // إضافة دور داعم
                $user->assignRole('داعم');
                
                // إذا كان له داعم، احتفظ بعلاقة المشارك أيضاً
                if ($user->supporter_id) {
                    $user->assignRole('مشارك');
                    
                    // إنشاء علاقة في جدول العلاقات الهرمية
                    $user->allSupporters()->syncWithoutDetaching([$user->supporter_id]);
                }
            }
            
            // تحديث تاريخ تغيير الدور
            $user->update(['role_changed_at' => now()]);
            
            return true;
        });
    }

    /**
     * Get user hierarchy tree.
     */
    public function getUserHierarchy(User $user): array
    {
        $hierarchy = [];
        
        // إذا كان داعم، احصل على المشاركين
        if ($user->hasRole('داعم')) {
            $hierarchy['as_supporter'] = [
                'direct_participants' => $user->participants()->with('roles')->get(),
                'all_participants' => $user->allParticipants()->with('roles')->get(),
            ];
        }
        
        // إذا كان مشارك، احصل على الداعمين
        if ($user->hasRole('مشارك')) {
            $hierarchy['as_participant'] = [
                'direct_supporter' => $user->supporter,
                'all_supporters' => $user->allSupporters()->with('roles')->get(),
            ];
        }
        
        return $hierarchy;
    }

    /**
     * Check if user can manage another user.
     */
    public function canManageUser(User $manager, User $target): bool
    {
        // مدير النظام يمكنه إدارة الجميع
        if ($manager->hasRole('مدير نظام')) {
            return true;
        }
        
        // الداعم يمكنه إدارة المشاركين التابعين له
        if ($manager->hasRole('داعم') && $target->hasRole('مشارك')) {
            return $target->supporter_id === $manager->id || 
                   $manager->allParticipants()->where('users.id', $target->id)->exists();
        }
        
        return false;
    }
}