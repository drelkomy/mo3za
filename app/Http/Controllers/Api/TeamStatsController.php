<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\MemberTaskStatsRequest;
use App\Http\Requests\Api\TeamMembersTaskStatsRequest;
use App\Http\Resources\MemberTaskResource;
use App\Http\Resources\MemberTaskStatsResource;
use App\Http\Resources\TeamMemberStatsResource;
use App\Http\Resources\TeamSummaryResource;
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
        
        // مسح كاش إحصائيات الفريق
        for ($i = 1; $i <= 3; $i++) {
            Cache::forget("team_members_task_stats_{$team->id}_page_{$i}_per_10");
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
            
            // جلب إجمالي المهام للفريق
            $allTeamTasks = Task::where('creator_id', $team->owner_id)
                ->select('id', 'title', 'receiver_id', 'status', 'progress', 'due_date', 'created_at')
                ->get();
                
            // جلب العلاقة بين المهام والأعضاء
            $taskUserRelations = DB::table('task_user')
                ->whereIn('task_id', $allTeamTasks->pluck('id')->toArray())
                ->get();
                
            // إجمالي المهام للفريق
            $totalTeamTasks = $allTeamTasks->count();
            $completedTeamTasks = $allTeamTasks->where('status', 'completed')->count();
            $pendingTeamTasks = $allTeamTasks->where('status', 'pending')->count();
            $inProgressTeamTasks = $allTeamTasks->where('status', 'in_progress')->count();
            
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
                    'avatar_url' => $member->avatar_url ? \Illuminate\Support\Facades\Storage::url($member->avatar_url) : asset('default-avatar.png'),
                    'total_tasks' => $totalTasks,
                    'completed_tasks' => $completedTasks,
                    'pending_tasks' => $pendingTasks,
                    'in_progress_tasks' => $inProgressTasks,
                    'completion_percentage_margin' => $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 2) . '%' : '0%'
                ];
            }
            
            // إضافة إجمالي الفريق
            $teamSummary = [
                'total_tasks' => $totalTeamTasks,
                'completed_tasks' => $completedTeamTasks,
                'pending_tasks' => $pendingTeamTasks,
                'in_progress_tasks' => $inProgressTeamTasks,
                'completion_rate' => $totalTeamTasks > 0 ? round(($completedTeamTasks / $totalTeamTasks) * 100, 2) : 0
            ];
            
            return response()->json([
                'message' => 'تم جلب إحصائيات أعضاء الفريق بنجاح',
                'data' => TeamMemberStatsResource::collection($result)
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
        $page = $request->input('page', 1);
        $perPage = 10;
        $team = Team::where('owner_id', auth()->id())->first();
        
        if (!$team) {
            return response()->json(['message' => 'أنت لست قائد فريق'], 403);
        }
        
        // التحقق من أن العضو ينتمي للفريق
        $isMember = $team->members()->where('user_id', $memberId)->exists();
        if (!$isMember) {
            return response()->json(['message' => 'العضو ليس في فريقك'], 403);
        }
        
        $cacheKey = "member_task_stats_{$team->id}_{$memberId}_page_{$page}";
        
        $data = Cache::remember($cacheKey, 300, function () use ($team, $memberId, $page, $perPage) {
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
                ->skip(($page - 1) * $perPage)
                ->take($perPage)
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
                'tasks' => $tasks,
                'pagination' => [
                    'total' => $totalTasks,
                    'per_page' => $perPage,
                    'current_page' => $page,
                    'last_page' => ceil($totalTasks / $perPage)
                ]
            ];
        });
        
        return response()->json([
            'message' => 'تم جلب إحصائيات مهام العضو بنجاح',
            'data' => [
                'member' => new MemberTaskStatsResource($data['stats']),
                'tasks' => MemberTaskResource::collection($data['tasks']),
                'pagination' => $data['pagination']
            ]
        ])->setMaxAge(300)->setPublic();
    }
    
    /**
     * إحصائيات مهام جميع أعضاء الفريق
     */
    public function getTeamMembersTaskStats(TeamMembersTaskStatsRequest $request): JsonResponse
    {
        $page = $request->input('page', 1);
        $perPage = min($request->input('per_page', 10), 50);
        $team = Team::where('owner_id', auth()->id())->first();
        
        if (!$team) {
            return response()->json(['message' => 'أنت لست قائد فريق'], 403);
        }
        
        $cacheKey = "team_members_task_stats_{$team->id}_page_{$page}_per_{$perPage}";
        
        $data = Cache::remember($cacheKey, 300, function () use ($team, $page, $perPage) {
            // جلب أعضاء الفريق مع التصفيح
            $members = $team->members()
                ->select('users.id', 'name', 'email', 'avatar_url')
                ->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();
            
            $totalMembers = $team->members()->count();
            
            // جلب جميع مهام الفريق مرة واحدة
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
                $averageProgress = $memberTasks->avg('progress') ?? 0;
                
                // المهام الأخيرة (5 مهام)
                $recentTasks = $memberTasks->sortByDesc('created_at')->take(5)->map(function ($task) {
                    return [
                        'id' => $task->id,
                        'title' => $task->title,
                        'status' => $task->status,
                        'progress' => $task->progress,
                        'due_date' => $task->due_date ? $task->due_date->format('Y-m-d') : null,
                        'created_at' => $task->created_at->format('Y-m-d'),
                        'stages_count' => $task->stages->count(),
                        'completed_stages' => $task->stages->where('status', 'completed')->count()
                    ];
                })->values();
                
                $membersData[] = [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'avatar_url' => $member->avatar_url ? \Illuminate\Support\Facades\Storage::url($member->avatar_url) : asset('default-avatar.png'),
                    'total_tasks' => $totalTasks,
                    'completed_tasks' => $completedTasks,
                    'pending_tasks' => $pendingTasks,
                    'in_progress_tasks' => $inProgressTasks,
                    'completion_rate' => $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 2) : 0,
                    'completion_percentage_margin' => $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 2) . '%' : '0%',
                    'average_progress' => round($averageProgress, 1),
                    'recent_tasks' => $recentTasks
                ];
            }
            
            // إحصائيات الفريق الإجمالية
            $totalTeamTasks = $allTeamTasks->count();
            $completedTeamTasks = $allTeamTasks->where('status', 'completed')->count();
            $pendingTeamTasks = $allTeamTasks->where('status', 'pending')->count();
            $inProgressTeamTasks = $allTeamTasks->where('status', 'in_progress')->count();
            
            return [
                'members' => $membersData,
                'team_summary' => [
                    'total_tasks' => $totalTeamTasks,
                    'completed_tasks' => $completedTeamTasks,
                    'pending_tasks' => $pendingTeamTasks,
                    'in_progress_tasks' => $inProgressTeamTasks,
                    'completion_rate' => $totalTeamTasks > 0 ? round(($completedTeamTasks / $totalTeamTasks) * 100, 2) : 0
                ],
                'pagination' => [
                    'total' => $totalMembers,
                    'per_page' => $perPage,
                    'current_page' => $page,
                    'last_page' => ceil($totalMembers / $perPage)
                ]
            ];
        });
        
        return response()->json([
            'message' => 'تم جلب إحصائيات مهام أعضاء الفريق بنجاح',
            'data' => [
                'members' => TeamMemberStatsResource::collection($data['members']),
                'team_summary' => new TeamSummaryResource($data['team_summary']),
                'pagination' => $data['pagination']
            ]
        ])->setMaxAge(300)->setPublic();
    }
}