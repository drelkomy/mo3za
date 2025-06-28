<?php

namespace App\Services;

use App\Models\Team;
use Illuminate\Support\Facades\Cache;

class CacheService
{
    /**
     * مسح كاش الفريق في الحالات الحرجة فقط
     */
    public static function clearTeamCache(int $creatorId, int $receiverId, bool $forceFullClear = false): void
    {
        $team = Team::where('owner_id', $creatorId)->first();
        if (!$team) return;
        
        $teamId = $team->id;
        
        // مسح محدود للمفاتيح الأساسية فقط
        $criticalKeys = [
            "my_team_{$creatorId}",
            "user_team_{$creatorId}",
            "team_members_{$teamId}_{$creatorId}",
            "member_stats_{$creatorId}",
            "my_rewards_{$receiverId}"
        ];
        
        foreach ($criticalKeys as $key) {
            Cache::forget($key);
        }
        
        // مسح شامل فقط في الحالات الحرجة
        if ($forceFullClear) {
            static::fullTeamCacheClear($teamId, $creatorId, $receiverId);
        }
    }
    
    /**
     * مسح شامل للفريق (فقط في الحالات الحرجة)
     */
    private static function fullTeamCacheClear(int $teamId, int $creatorId, int $receiverId): void
    {
        // مسح كاش مهام الفريق
        foreach (['all', 'pending', 'completed'] as $status) {
            for ($page = 1; $page <= 2; $page++) {
                Cache::forget("team_tasks_{$creatorId}_page_{$page}_per_10_status_{$status}_stages_true_counts_true_q_none");
            }
        }
        
        // مسح كاش مكافآت الفريق
        Cache::forget("team_rewards_{$teamId}_{$creatorId}");
        Cache::forget("my_rewards_data_{$receiverId}_page_1_per_10_status_all");
    }
    
    /**
     * مسح كاش عند حذف الفريق (حالة حرجة)
     */
    public static function clearOnTeamDelete(int $teamId, int $ownerId): void
    {
        // مسح شامل عند حذف الفريق
        Cache::flush(); // فقط في حالة حذف الفريق
    }
    
    /**
     * تحديث كاش بدلاً من مسحه
     */
    public static function refreshTeamData(int $teamId, callable $dataCallback, int $minutes = 5): mixed
    {
        $key = "team_data_{$teamId}_" . md5(serialize($dataCallback));
        return Cache::put($key, $dataCallback(), now()->addMinutes($minutes));
    }
}