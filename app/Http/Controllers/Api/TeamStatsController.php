<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\MemberTaskStatsRequest;
use App\Http\Requests\Api\TeamMembersTaskStatsRequest;
use App\Http\Resources\MemberTaskResource;
use App\Http\Resources\MemberTaskStatsResource;
use App\Http\Resources\TeamMemberStatsResource;
use App\Http\Resources\TeamSummaryResource;
use App\Http\Resources\MemberTaskSummaryResource;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TeamStatsController extends Controller
{
    /**
     * مسح كاش الفريق والأعضاء
     */
    private function clearTeamCache(Team $team): void
    {
        // مسح كاش الفريق
        Cache::forget("my_team_" . $team->owner_id);
        Cache::forget("user_team_" . $team->owner_id);
        
        // مسح كاش المهام
        for ($i = 1; $i <= 5; $i++) { // مسح أول 5 صفحات
            Cache::forget("team_tasks_{$team->id}_page_{$i}_per_10_status_");
            Cache::forget("team_tasks_{$team->id}_page_{$i}_per_10_status_completed");
            Cache::forget("team_tasks_{$team->id}_page_{$i}_per_10_status_pending");
            Cache::forget("team_tasks_{$team->id}_page_{$i}_per_10_status_in_progress");
        }
        
        // مسح كاش المكافآت
        for ($i = 1; $i <= 5; $i++) {
            Cache::forget("team_rewards_{$team->id}_page_{$i}_per_10_status_");
        }
        
        // مسح كاش إحصائيات الأعضاء
        $memberIds = $team->members()->pluck('user_id')->toArray();
        foreach ($memberIds as $memberId) {
            Cache::forget("my_team_" . $memberId);
            Cache::forget("user_team_" . $memberId);
            
            // مسح كاش إحصائيات مهام العضو
            for ($i = 1; $i <= 3; $i++) {
                Cache::forget("member_task_stats_{$team->id}_{$memberId}_page_{$i}");
            }
            
            // مسح كاش مهام العضو
            Cache::forget("my_tasks_" . $memberId);
        }
        
        // مسح كاش إحصائيات الفريق لجميع الحالات الممكنة
        $statuses = ['', 'pending', 'in_progress', 'completed'];
        $perPages = [10, 20, 30, 40, 50];
        for ($i = 1; $i <= 3; $i++) {
            foreach ($statuses as $status) {
                foreach ($perPages as $perPage) {
                    Cache::forget("team_members_task_stats_{$team->id}_page_{$i}_per_{$perPage}_status_{$status}");
                }
            }
        }
    }
    /**
     * عرض إحصائيات أعضاء الفريق
     */
    public function getMemberStats(\App\Http\Requests\Api\MemberStatsRequest $request): JsonResponse
    {
        try {
            // التحقق من المصادقة
            if (!auth()->check()) {
                return response()->json(['message' => 'غير مصادق'], 401);
            }
            
            $team = Team::where('owner_id', auth()->id())->first();
            
            if (!$team) {
                return response()->json(['message' => 'أنت لست قائد فريق'], 403);
            }

            // جلب أعضاء الفريق
            $members = $team->members()->get(['users.id', 'name', 'email', 'avatar_url']);
            
            // جلب مهام الفريق لحساب إحصائيات الأعضاء
            $allTeamTasks = Task::where('creator_id', $team->owner_id)
                ->select('id', 'title', 'receiver_id', 'status', 'progress', 'due_date', 'created_at')
                ->get();
                
            // جلب العلاقة بين المهام والأعضاء
            $taskUserRelations = DB::table('task_user')
                ->whereIn('task_id', $allTeamTasks->pluck('id')->toArray())
                ->get();
            
            $result = [];
            
            foreach ($members as $member) {
                // فلترة مهام العضو
                $memberTasks = $allTeamTasks->filter(function ($task) use ($member, $taskUserRelations) {
                    // المهام التي يكون العضو مستلمها
                    if ($task->receiver_id == $member->id) {
                        return true;
                    }
                    
                    // المهام التي يكون العضو مشاركاً فيها
                    foreach ($taskUserRelations as $relation) {
                        if ($relation->task_id == $task->id && $relation->user_id == $member->id) {
                            return true;
                        }
                    }
                    
                    return false;
                });
                
                // إحصائيات مهام العضو
                $totalTasks = $memberTasks->count();
                $completedTasks = $memberTasks->where('status', 'completed')->count();
                $pendingTasks = $memberTasks->where('status', 'pending')->count();
                $inProgressTasks = $memberTasks->where('status', 'in_progress')->count();
                
                // إضافة العضو إلى النتيجة
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
            

            
            return response()->json([
                'message' => 'تم جلب إحصائيات أعضاء الفريق بنجاح',
                'data' => [
                    'members' => TeamMemberStatsResource::collection($result)
                ]
            ])->setMaxAge(300)->setPublic();
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'حدث خطأ أثناء جلب إحصائيات أعضاء الفريق',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * إحصائيات مهام عضو محدد
     */
    public function getMemberTaskStats(MemberTaskStatsRequest $request): JsonResponse
    {
        $memberId = $request->input('member_id');
        $team = Team::where('owner_id', auth()->id())->first();
        
        if (!$team) {
            return response()->json(['message' => 'أنت لست قائد فريق'], 403);
        }
        
        // التحقق من أن العضو ينتمي للفريق
        $isMember = $team->members()->where('user_id', $memberId)->exists();
        if (!$isMember) {
            return response()->json(['message' => 'العضو ليس في فريقك'], 403);
        }
        
        $cacheKey = "member_task_stats_{$team->id}_{$memberId}";
        
        $data = Cache::remember($cacheKey, 300, function () use ($team, $memberId) {
            $member = User::find($memberId);
            
            // جلب المهام مع المراحل
            $tasksQuery = Task::where('creator_id', $team->owner_id)
                ->where(function ($query) use ($memberId) {
                    $query->where('receiver_id', $memberId)
                          ->orWhereHas('members', function ($q) use ($memberId) {
                              $q->where('user_id', $memberId);
                          });
                })
                ->with(['stages' => function($q) {
                    $q->orderBy('stage_number');
                }]);
                
            $totalTasks = $tasksQuery->count();
            
            $tasks = $tasksQuery->orderBy('created_at', 'desc')
                ->get();
                
            $completedTasks = Task::where('creator_id', $team->owner_id)
                ->where('status', 'completed')
                ->where(function ($query) use ($memberId) {
                    $query->where('receiver_id', $memberId)
                          ->orWhereHas('members', function ($q) use ($memberId) {
                              $q->where('user_id', $memberId);
                          });
                })
                ->count();
                
            $inProgressTasks = Task::where('creator_id', $team->owner_id)
                ->where('status', 'in_progress')
                ->where(function ($query) use ($memberId) {
                    $query->where('receiver_id', $memberId)
                          ->orWhereHas('members', function ($q) use ($memberId) {
                              $q->where('user_id', $memberId);
                          });
                })
                ->count();
                
            $pendingTasks = Task::where('creator_id', $team->owner_id)
                ->where('status', 'pending')
                ->where(function ($query) use ($memberId) {
                    $query->where('receiver_id', $memberId)
                          ->orWhereHas('members', function ($q) use ($memberId) {
                              $q->where('user_id', $memberId);
                          });
                })
                ->count();
                
            $averageProgress = Task::where('creator_id', $team->owner_id)
                ->where(function ($query) use ($memberId) {
                    $query->where('receiver_id', $memberId)
                          ->orWhereHas('members', function ($q) use ($memberId) {
                              $q->where('user_id', $memberId);
                          });
                })
                ->avg('progress') ?? 0;
                
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
        $status = $request->input('status');
        $team = Team::where('owner_id', auth()->id())->first();
        
        if (!$team) {
            return response()->json(['message' => 'أنت لست قائد فريق'], 403);
        }
        
        $cacheKey = "team_members_task_stats_{$team->id}_status_{$status}";
        
        $data = Cache::remember($cacheKey, 300, function () use ($team, $status) {
            // جلب جميع أعضاء الفريق
            $memberIds = $team->members()->pluck('user_id');
            
            $members = User::whereIn('id', $memberIds)
                ->select('id', 'name', 'email', 'avatar_url')
                ->get();
            
            // جلب جميع مهام الفريق مرة واحدة بناءً على creator_id
            $allTeamTasks = Task::where('creator_id', $team->owner_id)
                ->with(['stages' => function($q) {
                    $q->orderBy('stage_number');
                }])
                ->get();
                
            // جلب العلاقة بين المهام والأعضاء
            $taskUserRelations = DB::table('task_user')
                ->whereIn('task_id', $allTeamTasks->pluck('id')->toArray())
                ->get();
            
            $membersData = [];
            
            foreach ($members as $member) {
                // فلترة مهام العضو (استبعاد مهام مالك الفريق)
                $memberTasks = $allTeamTasks->filter(function ($task) use ($member, $taskUserRelations, $team) {
                    // تجاهل المهام التي يستلمها مالك الفريق
                    if ($task->receiver_id == $team->owner_id) {
                        return false;
                    }
                    
                    // المهام التي يكون العضو مستلمها
                    if ($task->receiver_id == $member->id) {
                        return true;
                    }
                    
                    // المهام التي يكون العضو مشاركاً فيها
                    foreach ($taskUserRelations as $relation) {
                        if ($relation->task_id == $task->id && $relation->user_id == $member->id) {
                            return true;
                        }
                    }
                    
                    return false;
                });
                
                // إحصائيات مهام العضو
                $totalTasks = $memberTasks->count();
                $completedTasks = $memberTasks->where('status', 'completed')->count();
                $pendingTasks = $memberTasks->where('status', 'pending')->count();
                $inProgressTasks = $memberTasks->where('status', 'in_progress')->count();
                $averageProgress = $memberTasks->avg('progress') ?? 0;
                
                // فلترة المهام حسب الحالة إذا تم تحديدها
                if ($status) {
                    $memberTasks = $memberTasks->where('status', $status);
                }
                
                // جلب جميع مهام العضو
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
