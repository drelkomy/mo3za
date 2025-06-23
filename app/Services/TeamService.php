<?php

namespace App\Services;

use App\Models\Team;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TeamService
{
    public function getMyTeamOptimized(int $userId): ?Team
    {
        return Cache::remember("optimized_team_{$userId}", 600, function () use ($userId) {
            return Team::select(['id', 'name', 'owner_id', 'created_at'])
                ->where('owner_id', $userId)
                ->withCount('members')
                ->with('owner:id,name')
                ->first();
        });
    }

    public function getMyTeamWithMembers(int $userId): ?Team
    {
        return Team::select(['id', 'name', 'owner_id', 'created_at'])
            ->where('owner_id', $userId)
            ->with([
                'owner:id,name',
                'members:id,name'
            ])
            ->first();
    }

    public function clearTeamCache(int $userId): void
    {
        Cache::forget("optimized_team_{$userId}");
        Cache::forget("my_team_{$userId}");
    }

    public function getTeamStats(int $teamId): array
    {
        return Cache::remember("team_stats_{$teamId}", 300, function () use ($teamId) {
            return DB::table('tasks')
                ->where('team_id', $teamId)
                ->selectRaw('
                    COUNT(*) as total_tasks,
                    COUNT(CASE WHEN status = "completed" THEN 1 END) as completed_tasks,
                    AVG(progress) as avg_progress
                ')
                ->first();
        });
    }
}
