<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\MemberTaskStatsRequest;
use App\Http\Requests\Api\TeamMembersTaskStatsRequest;
use App\Http\Resources\MemberTaskResource;
use App\Http\Resources\MemberTaskStatsResource;
use App\Http\Resources\TeamMemberStatsResource;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TeamStatsController extends Controller
{
    /**
     * مسح كاش المستخدم الحالي فقط
     */
    private function clearUserCache(int $userId): void
    {
        $patterns = [
            "member_stats_{$userId}",
            "member_task_stats_{$userId}",
            "team_members_task_stats_{$userId}"
        ];
        
        foreach ($patterns as $pattern) {
            Cache::forget($pattern . '_all');
            Cache::forget($pattern . '_pending');
            Cache::forget($pattern . '_completed');
            Cache::forget($pattern . '_in_progress');
        }
    }
    
    /**
     * عرض إحصائيات أعضاء الفريق
     */
    public function getMemberStats(\App\Http\Requests\Api\MemberStatsRequest $request): JsonResponse
    {
        $userId = auth()->id();
        $cacheKey = "member_stats_" . $userId;
        
        $data = Cache::remember($cacheKey, 300, function () use ($userId) {
            $team = Team::where('owner_id', $userId)->first();
            
            if (!$team) {
                return null;
            }

            $members = $team->members()->get(['users.id', 'name', 'email', 'avatar_url']);
            $allTeamTasks = Task::where('creator_id', $userId)
                ->select('id', 'title', 'receiver_id', 'status', 'progress', 'due_date', 'created_at')
                ->get();
                
            $taskUserRelations = DB::table('task_user')
                ->whereIn('task_id', $allTeamTasks->pluck('id')->toArray())
                ->get();
            
            $result = [];
            
            foreach ($members as $member) {
                $memberTasks = $allTeamTasks->filter(function ($task) use ($member, $taskUserRelations) {
                    if ($task->receiver_id == $member->id) {
                        return true;
                    }
                    
                    foreach ($taskUserRelations as $relation) {
                        if ($relation->task_id == $task->id && $relation->user_id == $member->id) {
                            return true;
                        }
                    }
                    
                    return false;
                });
                
                $totalTasks = $memberTasks->count();
                $completedTasks = $memberTasks->where('status', 'completed')->count();
                $pendingTasks = $memberTasks->where('status', 'pending')->count();
                $inProgressTasks = $memberTasks->where('status', 'in_progress')->count();
                
                $result[] = [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'avatar_url' => $this->getAvatarUrl($member),
                    'total_tasks' => $totalTasks,
                    'completed_tasks' => $completedTasks,
                    'pending_tasks' => $pendingTasks,
                    'in_progress_tasks' => $inProgressTasks,
                    'completion_rate' => $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 2) : 0,
                    'completion_percentage_margin' => $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 2) . '%' : '0%'
                ];
            }
            
            return $result;
        });
        
        if (!$data) {
            return response()->json(['message' => 'أنت لست قائد فريق'], 403);
        }
        
        return response()->json([
            'message' => 'تم جلب إحصائيات أعضاء الفريق بنجاح',
            'data' => [
                'members' => TeamMemberStatsResource::collection($data)
            ]
        ])->setMaxAge(300)->setPublic();
    }

    /**
     * إحصائيات مهام عضو محدد
     */
    public function getMemberTaskStats(MemberTaskStatsRequest $request): JsonResponse
    {
        $userId = auth()->id();
        $memberId = $request->input('member_id');
        $cacheKey = "member_task_stats_{$userId}_{$memberId}";
        
        $data = Cache::remember($cacheKey, 300, function () use ($userId, $memberId) {
            $team = Team::where('owner_id', $userId)->first();
            
            if (!$team) {
                return null;
            }
            
            $isMember = $team->members()->where('user_id', $memberId)->exists();
            if (!$isMember) {
                return false;
            }
            
            $member = User::find($memberId);
            
            $tasksQuery = Task::where('creator_id', $userId)
                ->where(function ($query) use ($memberId) {
                    $query->where('receiver_id', $memberId)
                          ->orWhereHas('members', function ($q) use ($memberId) {
                              $q->where('user_id', $memberId);
                          });
                })
                ->with('stages');
                
            $totalTasks = $tasksQuery->count();
            $tasks = $tasksQuery->orderBy('created_at', 'desc')->get();
                
            $completedTasks = $tasks->where('status', 'completed')->count();
            $inProgressTasks = $tasks->where('status', 'in_progress')->count();
            $pendingTasks = $tasks->where('status', 'pending')->count();
            $averageProgress = $tasks->avg('progress') ?? 0;
                
            return [
                'stats' => [
                    'member_id' => $memberId,
                    'member_name' => $member->name,
                    'total_tasks' => $totalTasks,
                    'completed_tasks' => $completedTasks,
                    'in_progress_tasks' => $inProgressTasks,
                    'pending_tasks' => $pendingTasks,
                    'completion_rate' => $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 2) : 0,
                    'average_progress' => round($averageProgress, 1)
                ],
                'tasks' => $tasks
            ];
        });
        
        if ($data === null) {
            return response()->json(['message' => 'أنت لست قائد فريق'], 403);
        }
        
        if ($data === false) {
            return response()->json(['message' => 'العضو ليس في فريقك'], 403);
        }
        
        return response()->json([
            'message' => 'تم جلب إحصائيات مهام العضو بنجاح',
            'data' => [
                'member' => new MemberTaskStatsResource($data['stats']),
                'tasks' => MemberTaskResource::collection($data['tasks'])
            ]
        ])->setMaxAge(300)->setPublic();
    }
    
    /**
     * إحصائيات مهام جميع أعضاء الفريق
     */
    public function getTeamMembersTaskStats(TeamMembersTaskStatsRequest $request): JsonResponse
    {
        $userId = auth()->id();
        $status = $request->input('status');
        $statusKey = $status ?: 'all';
        $cacheKey = "team_members_task_stats_{$userId}_{$statusKey}";
        
        $data = Cache::remember($cacheKey, 300, function () use ($userId, $status) {
            $team = Team::where('owner_id', $userId)->first();
            
            if (!$team) {
                return null;
            }
            
            $memberIds = $team->members()->pluck('user_id');
            $members = User::whereIn('id', $memberIds)
                ->select('id', 'name', 'email', 'avatar_url')
                ->get();
            
            $allTeamTasks = Task::where('creator_id', $userId)
                ->with('stages')
                ->get();
                
            $taskUserRelations = DB::table('task_user')
                ->whereIn('task_id', $allTeamTasks->pluck('id')->toArray())
                ->get();
            
            $membersData = [];
            
            foreach ($members as $member) {
                $memberTasks = $allTeamTasks->filter(function ($task) use ($member, $taskUserRelations, $userId) {
                    if ($task->receiver_id == $userId) {
                        return false;
                    }
                    
                    if ($task->receiver_id == $member->id) {
                        return true;
                    }
                    
                    foreach ($taskUserRelations as $relation) {
                        if ($relation->task_id == $task->id && $relation->user_id == $member->id) {
                            return true;
                        }
                    }
                    
                    return false;
                });
                
                $totalTasks = $memberTasks->count();
                $completedTasks = $memberTasks->where('status', 'completed')->count();
                $pendingTasks = $memberTasks->where('status', 'pending')->count();
                $inProgressTasks = $memberTasks->where('status', 'in_progress')->count();
                $averageProgress = $memberTasks->avg('progress') ?? 0;
                
                if ($status) {
                    $memberTasks = $memberTasks->where('status', $status);
                }
                
                $allMemberTasks = $memberTasks->sortByDesc('created_at')->values()->map(function ($task) {
                    return [
                        'id' => $task->id,
                        'title' => $task->title,
                        'status' => $task->status,
                        'progress' => $task->progress,
                        'due_date' => $task->due_date ? $task->due_date->format('Y-m-d') : null,
                        'created_at' => $task->created_at->format('Y-m-d'),
                        'stages_count' => $task->stages ? $task->stages->count() : 0,
                        'completed_stages' => $task->stages ? $task->stages->where('status', 'completed')->count() : 0
                    ];
                });
                
                $membersData[] = [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'avatar_url' => $this->getAvatarUrl($member),
                    'total_tasks' => $totalTasks,
                    'completed_tasks' => $completedTasks,
                    'pending_tasks' => $pendingTasks,
                    'in_progress_tasks' => $inProgressTasks,
                    'completion_rate' => $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 2) : 0,
                    'completion_percentage_margin' => $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 2) . '%' : '0%',
                    'average_progress' => round($averageProgress, 1),
                    'tasks' => $allMemberTasks
                ];
            }
            
            return [
                'members' => $membersData
            ];
        });
        
        if (!$data) {
            return response()->json(['message' => 'أنت لست قائد فريق'], 403);
        }
        
        return response()->json([
            'message' => 'تم جلب إحصائيات مهام أعضاء الفريق بنجاح',
            'data' => [
                'members' => TeamMemberStatsResource::collection($data['members'])
            ]
        ])->setMaxAge(300)->setPublic();
    }
    
    /**
     * الحصول على رابط الصورة الشخصية
     */
    private function getAvatarUrl($user)
    {
        if (!$user || empty($user->avatar_url)) {
            return null;
        }
        
        if (str_starts_with($user->avatar_url, 'http')) {
            $url = $user->avatar_url;
            if (str_contains($url, 'storage/https://')) {
                $url = str_replace('https://www.moezez.com/storage/https://www.moezez.com/storage/', 'https://www.moezez.com/storage/', $url);
            }
            return $url;
        }
        
        if (str_starts_with($user->avatar_url, 'avatars/')) {
            return 'https://www.moezez.com/storage/' . $user->avatar_url;
        }
        
        if (str_starts_with($user->avatar_url, 'storage/')) {
            return 'https://www.moezez.com/' . $user->avatar_url;
        }
        
        return 'https://www.moezez.com/storage/' . $user->avatar_url;
    }
}