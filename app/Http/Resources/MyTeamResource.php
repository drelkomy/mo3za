<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MyTeamResource extends JsonResource
{
    private function getAvatarUrl($user)
    {
        if (!$user) return null;
        
        // التحقق من وجود صورة للمستخدم
        if ($user->avatar_url) {
            // استخدام نفس طريقة البروفايل بالضبط
            return Storage::url($user->avatar_url);
        }
        
        return asset('default-avatar.png');
    }
    
    public function toArray($request)
    {
        $currentUserId = auth()->id();
        $isOwner = $this->owner_id === $currentUserId;
        
        return [
            'id' => $this->id,
            'name' => $this->name,
            'is_owner' => $isOwner,
            'owner' => [
                'id' => $this->owner->id,
                'name' => $this->owner->name,
                'email' => $this->owner->email,
            ],
            'members' => $this->formatMembers(),
            'members_count' => $this->members->count(),
            'tasks_summary' => $this->getTasksSummary(),
            'created_at' => $this->created_at->format('Y-m-d'),
        ];
    }

    private function formatMembers(): Collection
    {
        if (!$this->members || $this->members->isEmpty()) {
            return collect();
        }

        $memberStats = $this->getMemberStats();

        return $this->members->map(function($member) use ($memberStats) {
            $stats = $memberStats->get($member->id, [
                'tasks_count' => 0,
                'completed_tasks' => 0,
                'average_progress' => 0
            ]);

            return [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'avatar_url' => $this->getAvatarUrl($member),
                'tasks_count' => $stats['tasks_count'],
                'completed_tasks' => $stats['completed_tasks'],
                'average_progress' => $stats['average_progress'],
                'completion_percentage_margin' => $stats['tasks_count'] > 0 ? round(($stats['completed_tasks'] / $stats['tasks_count']) * 100, 2) . '%' : '0%',
                'recent_tasks' => $this->getRecentTasks($member->id)
            ];
        })->values();
    }

    private function getMemberStats(): Collection
    {
        if (!$this->members || $this->members->isEmpty()) {
            return collect();
        }

        $memberIds = $this->members->pluck('id')->toArray();
        $result = collect();
        
        // جلب إحصائيات المهام مباشرة من قاعدة البيانات
        foreach ($memberIds as $memberId) {
            $totalTasks = \App\Models\Task::where('creator_id', $this->owner_id)
                ->where(function ($query) use ($memberId) {
                    $query->where('receiver_id', $memberId)
                          ->orWhereHas('members', function ($q) use ($memberId) {
                              $q->where('user_id', $memberId);
                          });
                })
                ->count();
                
            $completedTasks = \App\Models\Task::where('creator_id', $this->owner_id)
                ->where('status', 'completed')
                ->where(function ($query) use ($memberId) {
                    $query->where('receiver_id', $memberId)
                          ->orWhereHas('members', function ($q) use ($memberId) {
                              $q->where('user_id', $memberId);
                          });
                })
                ->count();
                
            $averageProgress = \App\Models\Task::where('creator_id', $this->owner_id)
                ->where(function ($query) use ($memberId) {
                    $query->where('receiver_id', $memberId)
                          ->orWhereHas('members', function ($q) use ($memberId) {
                              $q->where('user_id', $memberId);
                          });
                })
                ->avg('progress') ?? 0;
            
            $result->put($memberId, [
                'tasks_count' => $totalTasks,
                'completed_tasks' => $completedTasks,
                'average_progress' => round($averageProgress, 1)
            ]);
        }
        
        return $result;
    }

    private function getRecentTasks($memberId): Collection
    {
        $tasks = \App\Models\Task::where('creator_id', $this->owner_id)
            ->where('receiver_id', $memberId)
            ->orderBy('id', 'desc')
            ->take(3)
            ->get(['id', 'title', 'status', 'progress']);
        
        return $tasks->map(function($task) {
            return [
                'id' => $task->id,
                'title' => $task->title,
                'status' => $task->status,
                'progress' => $task->progress,
            ];
        });
    }

    private function getTasksSummary(): array
    {
        $totalTasks = \App\Models\Task::where('creator_id', $this->owner_id)->count();
        $completedTasks = \App\Models\Task::where('creator_id', $this->owner_id)->where('status', 'completed')->count();
        $inProgressTasks = \App\Models\Task::where('creator_id', $this->owner_id)->where('status', 'in_progress')->count();

        return [
            'total' => $totalTasks,
            'completed' => $completedTasks,
            'in_progress' => $inProgressTasks,
            'pending' => $totalTasks - $completedTasks - $inProgressTasks,
            'completion_rate' => $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 1) : 0
        ];
    }
}
