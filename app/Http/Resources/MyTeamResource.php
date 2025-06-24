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
            return "https://www.moezez.com" . Storage::url($user->avatar_url);
        }
        
        return null;
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
                'recent_tasks' => $this->getRecentTasks($member->id)
            ];
        })->values();
    }

    private function getMemberStats(): Collection
    {
        if (!$this->members || $this->members->isEmpty() || !$this->tasks) {
            return collect();
        }

        $memberIds = $this->members->pluck('id')->toArray();
        $result = collect();
        
        // تجميع البيانات من المهام المحملة مسبقاً بدلاً من استعلام قاعدة البيانات مرة أخرى
        foreach ($memberIds as $memberId) {
            $memberTasks = $this->tasks->filter(function($task) use ($memberId) {
                return $task->receiver_id == $memberId;
            });
            
            if ($memberTasks->isNotEmpty()) {
                $tasksCount = $memberTasks->count();
                $completedTasks = $memberTasks->filter(function($task) {
                    return $task->status === 'completed';
                })->count();
                
                $progressSum = $memberTasks->sum('progress');
                $averageProgress = $tasksCount > 0 ? round($progressSum / $tasksCount, 1) : 0;
                
                $result->put($memberId, [
                    'tasks_count' => $tasksCount,
                    'completed_tasks' => $completedTasks,
                    'average_progress' => $averageProgress
                ]);
            }
        }
        
        return $result;
    }

    private function getRecentTasks($memberId): Collection
    {
        $tasks = $this->tasks->filter(function($task) use ($memberId) {
            return $task->receiver_id == $memberId;
        });
        
        return $tasks
            ->sortByDesc('id')
            ->take(3)
            ->map(function($task) {
                return [
                    'id' => $task->id,
                    'title' => $task->title,
                    'status' => $task->status,
                    'progress' => $task->progress,
                ];
            })
            ->values();
    }

    private function getTasksSummary(): array
    {
        if (!$this->tasks || $this->tasks->isEmpty()) {
            return [
                'total' => 0,
                'completed' => 0,
                'in_progress' => 0,
                'pending' => 0,
                'completion_rate' => 0
            ];
        }
        
        $totalTasks = $this->tasks->count();
        $completedTasks = $this->tasks->filter(function($task) {
            return $task->status === 'completed';
        })->count();
        
        $inProgressTasks = $this->tasks->filter(function($task) {
            return $task->status === 'in_progress';
        })->count();

        return [
            'total' => $totalTasks,
            'completed' => $completedTasks,
            'in_progress' => $inProgressTasks,
            'pending' => $totalTasks - $completedTasks - $inProgressTasks,
            'completion_rate' => $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 1) : 0
        ];
    }
}
