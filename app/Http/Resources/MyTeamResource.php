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
        if (!$user || empty($user->avatar_url)) {
            return null;
        }
        
        // إذا كان الرابط يحتوي على http بالفعل
        if (str_starts_with($user->avatar_url, 'http')) {
            // إزالة التكرار إذا وجد
            $url = $user->avatar_url;
            if (str_contains($url, 'storage/https://')) {
                $url = str_replace('https://www.moezez.com/storage/https://www.moezez.com/storage/', 'https://www.moezez.com/storage/', $url);
            }
            return $url;
        }
        
        // إذا كان يبدأ بـ avatars/
        if (str_starts_with($user->avatar_url, 'avatars/')) {
            return 'https://www.moezez.com/storage/' . $user->avatar_url;
        }
        
        // إذا كان يحتوي على storage/
        if (str_starts_with($user->avatar_url, 'storage/')) {
            return 'https://www.moezez.com/' . $user->avatar_url;
        }
        
        // الحالة الافتراضية
        return 'https://www.moezez.com/storage/' . $user->avatar_url;
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
                'all_tasks' => $this->getAllTasks($member->id)
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
            $memberTasks = \App\Models\Task::where('receiver_id', $memberId)->get();
            
            $totalTasks = $memberTasks->count();
            $completedTasks = $memberTasks->where('status', 'completed')->count();
            $averageProgress = $totalTasks > 0 ? $memberTasks->avg('progress') : 0;
            
            $result->put($memberId, [
                'tasks_count' => $totalTasks,
                'completed_tasks' => $completedTasks,
                'average_progress' => round($averageProgress, 1)
            ]);
        }
        
        return $result;
    }

    private function getAllTasks($memberId): Collection
    {
        $tasks = \App\Models\Task::where('receiver_id', $memberId)
            ->with(['creator:id,name', 'stages:id,task_id,title,status'])
            ->orderBy('created_at', 'desc')
            ->get(['id', 'title', 'description', 'status', 'progress', 'creator_id', 'created_at', 'due_date']);
        
        return $tasks->map(function($task) {
            return [
                'id' => $task->id,
                'title' => $task->title,
                'description' => strip_tags($task->description),
                'status' => $task->status,
                'progress' => $task->progress,
                'creator' => $task->creator ? [
                    'id' => $task->creator->id,
                    'name' => $task->creator->name
                ] : null,
                'stages_count' => $task->stages ? $task->stages->count() : 0,
                'completed_stages' => $task->stages ? $task->stages->where('status', 'completed')->count() : 0,
                'due_date' => $task->due_date ? $task->due_date->format('Y-m-d') : null,
                'created_at' => $task->created_at->format('Y-m-d H:i:s'),
            ];
        });
    }

    private function getTasksSummary(): array
    {
        // جلب مهام أعضاء الفريق فقط
        $teamMemberIds = $this->members->pluck('id')->push($this->owner_id)->toArray();
        $teamTasks = \App\Models\Task::whereIn('receiver_id', $teamMemberIds)->get();
        
        $totalTasks = $teamTasks->count();
        $completedTasks = $teamTasks->where('status', 'completed')->count();
        $inProgressTasks = $teamTasks->where('status', 'in_progress')->count();
        $pendingTasks = $teamTasks->where('status', 'pending')->count();

        return [
            'total' => $totalTasks,
            'completed' => $completedTasks,
            'in_progress' => $inProgressTasks,
            'pending' => $pendingTasks,
            'completion_rate' => $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 1) : 0
        ];
    }
}
