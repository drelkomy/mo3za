<?php

namespace App\Services;

use App\Models\Team;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;

class CacheService
{
    /**
     * مسح كاش الفريق المحدد فقط
     */
    public static function clearTeamCache(int $creatorId, int $receiverId): void
    {
        $team = Team::where('owner_id', $creatorId)->first();
        if (!$team) return;
        
        $teamId = $team->id;
        
        // مسح كاش مهام الفريق
        foreach (['all', 'pending', 'completed', 'in_progress'] as $status) {
            for ($page = 1; $page <= 3; $page++) {
                foreach ([10, 20, 50] as $perPage) {
                    Cache::forget("team_tasks_{$creatorId}_page_{$page}_per_{$perPage}_status_{$status}_stages_true_counts_true_q_none");
                    Cache::forget("team_tasks_{$creatorId}_page_{$page}_per_{$perPage}_status_{$status}_stages_false_counts_false_q_none");
                }
            }
        }
        
        // مسح كاش إحصائيات الفريق
        Cache::forget("member_stats_{$creatorId}");
        Cache::forget("team_members_task_stats_{$creatorId}_all");
        Cache::forget("member_task_stats_{$creatorId}_{$receiverId}");
        
        // مسح كاش مكافآت الفريق
        foreach (['', 'all', 'pending', 'received'] as $status) {
            for ($page = 1; $page <= 3; $page++) {
                foreach ([10, 20, 50] as $perPage) {
                    $statusSuffix = $status ? "_status_{$status}" : '';
                    Cache::forget("team_rewards_{$teamId}_page_{$page}_per_{$perPage}{$statusSuffix}_stats_true");
                    Cache::forget("team_rewards_{$teamId}_page_{$page}_per_{$perPage}{$statusSuffix}_stats_false");
                }
            }
        }
        Cache::forget("team_rewards_{$teamId}_{$creatorId}");
        Cache::forget("team_rewards_all_{$teamId}_{$creatorId}");
        
        // مسح كاش مكافآت المستقبل
        Cache::forget("my_rewards_{$receiverId}");
        foreach (['', 'all', 'pending', 'received'] as $status) {
            for ($page = 1; $page <= 3; $page++) {
                foreach ([5, 10, 20] as $perPage) {
                    $statusSuffix = $status ? "_status_{$status}" : '';
                    Cache::forget("my_rewards_data_{$receiverId}_page_{$page}_per_{$perPage}{$statusSuffix}");
                }
            }
        }
        
        // مسح كاش الفريق العام
        Cache::forget("my_team_{$creatorId}");
        Cache::forget("user_team_{$creatorId}");
        Cache::forget("team_members_{$teamId}_{$creatorId}");
        
        // مسح كاش الدعوات
        Cache::forget("team_invitations_{$creatorId}");
        Cache::forget("my_invitations_{$receiverId}");
        
        // مسح كاش البحث
        $types = ['all', 'task', 'stage', 'member'];
        $commonQueries = ['ا', 'م', 'ت', 'س', 'ع', 'ن', 'ل', 'ر', 'د', 'ك'];
        foreach ($types as $type) {
            foreach ($commonQueries as $query) {
                Cache::forget("search_{$creatorId}_{$type}_" . md5($query));
                Cache::forget("search_{$receiverId}_{$type}_" . md5($query));
            }
        }
        
        // مسح كاش Laravel العام للتأكد من ظهور التحديثات
        \Artisan::call('cache:clear');
        \Artisan::call('view:clear');
        \Artisan::call('route:clear');
    }
}