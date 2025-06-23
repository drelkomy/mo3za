<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CreateTeamRequest;
use App\Http\Requests\Api\MyTeamRequest;
use App\Http\Requests\Api\RemoveMemberRequest;
use App\Http\Requests\Api\UpdateTeamNameRequest;
use App\Http\Resources\MyTeamResource;
use App\Http\Resources\TeamDetailResource;
use App\Http\Resources\TeamResource;
use App\Models\Team;
use App\Models\User;
use App\Models\TeamMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use App\Services\TeamService;

class TeamController extends Controller
{
    public function create(CreateTeamRequest $request): JsonResponse
    {
        // إنشاء الفريق
        $team = Team::create([
            'name' => $request->input('name'),
            'owner_id' => auth()->id()
        ]);
        
        // تحميل العلاقات للريسورس
        $team->load('owner');
        
        return response()->json([
            'message' => 'تم إنشاء الفريق بنجاح',
            'team' => new TeamDetailResource($team)
        ], 201);
    }
    
    public function myTeam(MyTeamRequest $request, TeamService $teamService): JsonResponse
    {
        $team = $teamService->getMyTeamOptimized(auth()->id());
        
        if (!$team) {
            return response()->json([
                'message' => 'لا تملك فريقاً',
                'data' => null
            ]);
        }
        
        return response()->json([
            'message' => 'تم جلب فريقك بنجاح',
            'data' => new MyTeamResource($team)
        ])->setMaxAge(600)->setPublic();
    }
    
    public function updateName(UpdateTeamNameRequest $request): JsonResponse
    {
        $team = Team::where('owner_id', auth()->id())->firstOrFail();
        
        $team->update(['name' => $request->input('name')]);
        
        // تنظيف cache
        Cache::forget('my_owned_team_' . auth()->id());
        
        // تحميل العلاقات في استعلام واحد
        $team = Team::where('id', $team->id)->with('owner')->first();
        
        return response()->json([
            'message' => 'تم تعديل اسم الفريق بنجاح',
            'team' => new TeamDetailResource($team)
        ]);
    }
    
    public function removeMember(RemoveMemberRequest $request): JsonResponse
    {
        $team = Team::with('members')->where('owner_id', auth()->id())->firstOrFail();
        
        $memberId = $request->input('member_id');
        
        // التحقق من وجود العضو في الفريق
        if (!$team->members->contains('id', $memberId)) {
            return response()->json([
                'message' => 'العضو غير موجود في فريقك'
            ], 404);
        }
        
        // إزالة العضو
        $team->members()->detach($memberId);
        
        // تنظيف cache
        Cache::forget('my_owned_team_' . auth()->id());
        Cache::forget('my_owned_team_' . $memberId);
        
        return response()->json([
            'message' => 'تم إزالة العضو من فريقك بنجاح'
        ]);
    }

    public function createTask(\App\Http\Requests\Api\CreateTaskRequest $request): JsonResponse
    {
        $team = Team::where('owner_id', auth()->id())->firstOrFail();

        $taskService = app(\App\Services\TaskService::class);
        $subscription = auth()->user()->activeSubscription;
        
        if (!$subscription) {
            return response()->json(['message' => 'لا يوجد اشتراك نشط'], 403);
        }
        
        // التحقق من حدود الباقة
        $currentTasksCount = \App\Models\Task::where('subscription_id', $subscription->id)->count();
        $tasksToCreate = $request->is_multiple ? count($request->assigned_members ?? []) : 1;
        
        if (($currentTasksCount + $tasksToCreate) > $subscription->max_tasks) {
            return response()->json([
                'message' => 'تجاوزت حد المهام المسموح به في باقتك',
                'current_tasks' => $currentTasksCount,
                'max_tasks' => $subscription->max_tasks,
                'tasks_to_create' => $tasksToCreate
            ], 403);
        }
        
        // التحقق من عدد المراحل
        if ($request->total_stages > $subscription->max_milestones_per_task) {
            return response()->json([
                'message' => 'عدد المراحل يتجاوز الحد المسموح به',
                'requested_stages' => $request->total_stages,
                'max_stages' => $subscription->max_milestones_per_task
            ], 403);
        }

        $dueDate = \Carbon\Carbon::parse($request->start_date)->addDays($request->duration_days);
        
        if ($request->is_multiple && $request->assigned_members) {
            $tasks = [];
            foreach ($request->assigned_members as $memberId) {
                if (!$team->members()->where('user_id', $memberId)->exists()) {
                    continue;
                }
                
                $task = \App\Models\Task::create([
                    'title' => $request->title,
                    'description' => $request->description,
                    'creator_id' => auth()->id(),
                    'assigned_to' => $memberId,
                    'subscription_id' => $subscription->id,
                    'team_id' => $team->id,
                    'start_date' => $request->start_date,
                    'due_date' => $dueDate,
                    'duration_days' => $request->duration_days,
                    'total_stages' => $request->total_stages,
                    'reward_type' => $request->reward_type,
                    'reward_amount' => $request->reward_type === 'cash' ? $request->reward_amount : 0,
                    'reward_description' => $request->reward_description,
                    'status' => 'pending',
                    'progress' => 0,
                ]);
                
                $taskService->createTaskStages($task);
                $tasks[] = $task->load('assignedTo', 'creator');
                
                // تحديث عداد المهام في الاشتراك
                $subscription->increment('tasks_created');
            }
            
            return response()->json([
                'message' => 'تم إنشاء المهام بنجاح',
                'tasks' => \App\Http\Resources\TaskResource::collection($tasks)
            ], 201);
        } else {
            if (!$team->members()->where('user_id', $request->assigned_to)->exists()) {
                return response()->json(['message' => 'العضو المحدد ليس في فريقك'], 400);
            }
            
            $task = \App\Models\Task::create([
                'title' => $request->title,
                'description' => $request->description,
                'creator_id' => auth()->id(),
                'assigned_to' => $request->assigned_to,
                'subscription_id' => $subscription->id,
                'team_id' => $team->id,
                'start_date' => $request->start_date,
                'due_date' => $dueDate,
                'duration_days' => $request->duration_days,
                'total_stages' => $request->total_stages,
                'reward_type' => $request->reward_type,
                'reward_amount' => $request->reward_type === 'cash' ? $request->reward_amount : 0,
                'reward_description' => $request->reward_description,
                'status' => 'pending',
                'progress' => 0,
            ]);
            
            $taskService->createTaskStages($task);
            
            // تحديث عداد المهام في الاشتراك
            $subscription->increment('tasks_created');
            
            return response()->json([
                'message' => 'تم إنشاء المهمة بنجاح',
                'task' => new \App\Http\Resources\TaskResource($task->load('assignedTo', 'creator'))
            ], 201);
        }
    }

    public function getTeamTasks(Request $request): JsonResponse
    {
        $team = Team::where('owner_id', auth()->id())->firstOrFail();

        $page = $request->input('page', 1);
        $perPage = min($request->input('per_page', 10), 50);
        $status = $request->input('status');
        $assignedTo = $request->input('assigned_to');
        
        $cacheKey = "team_tasks_{$team->id}_page_{$page}_per_{$perPage}_status_{$status}_assigned_{$assignedTo}";
        
        $tasksData = Cache::remember($cacheKey, 300, function () use ($team, $page, $perPage, $status, $assignedTo) {
            $query = \App\Models\Task::where('team_id', $team->id)
                ->with(['assignedTo:id,name', 'creator:id,name', 'stages' => function($q) {
                    $q->orderBy('stage_number');
                }])
                ->withCount('stages');
            
            if ($status) {
                $query->where('status', $status);
            }
            
            if ($assignedTo) {
                $query->where('assigned_to', $assignedTo);
            }
            
            // فصل استعلام العداد
            $countQuery = clone $query;
            $total = $countQuery->count();
            
            $tasks = $query->orderBy('created_at', 'desc')
                ->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();
            
            return [
                'tasks' => $tasks,
                'total' => $total,
                'current_page' => $page,
                'per_page' => $perPage,
                'last_page' => ceil($total / $perPage)
            ];
        });
        
        return response()->json([
            'message' => 'تم جلب مهام الفريق بنجاح',
            'data' => \App\Http\Resources\TaskWithStagesResource::collection($tasksData['tasks']),
            'meta' => [
                'total' => $tasksData['total'],
                'current_page' => $tasksData['current_page'],
                'per_page' => $tasksData['per_page'],
                'last_page' => $tasksData['last_page']
            ]
        ])->setMaxAge(300)->setPublic();
    }

    public function getTeamRewards(Request $request): JsonResponse
    {
        $team = Team::where('owner_id', auth()->id())->firstOrFail();

        $page = $request->input('page', 1);
        $perPage = min($request->input('per_page', 10), 50);
        $status = $request->input('status');
        $userId = $request->input('user_id');
        
        $cacheKey = "team_rewards_{$team->id}_page_{$page}_per_{$perPage}_status_{$status}_user_{$userId}";
        
        $rewardsData = Cache::remember($cacheKey, 300, function () use ($team, $page, $perPage, $status, $userId) {
            $query = \App\Models\Reward::whereHas('task', function($q) use ($team) {
                    $q->where('team_id', $team->id);
                })
                ->with(['task:id,title', 'user:id,name', 'distributedBy:id,name'])
                ->orderBy('distributed_at', 'desc');
            
            if ($status) {
                $query->where('status', $status);
            }
            
            if ($userId) {
                $query->where('user_id', $userId);
            }
            
            // فصل استعلامات العداد والمجموع
            $countQuery = clone $query;
            $sumQuery = clone $query;
            $total = $countQuery->count();
            $totalAmount = $sumQuery->sum('amount');
            
            $rewards = $query->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();
            
            return [
                'rewards' => $rewards,
                'total' => $total,
                'total_amount' => $totalAmount,
                'current_page' => $page,
                'per_page' => $perPage,
                'last_page' => ceil($total / $perPage)
            ];
        });
        
        return response()->json([
            'message' => 'تم جلب مكافآت الفريق بنجاح',
            'data' => \App\Http\Resources\RewardResource::collection($rewardsData['rewards']),
            'meta' => [
                'total' => $rewardsData['total'],
                'total_amount' => $rewardsData['total_amount'],
                'current_page' => $rewardsData['current_page'],
                'per_page' => $rewardsData['per_page'],
                'last_page' => $rewardsData['last_page']
            ]
        ])->setMaxAge(300)->setPublic();
    }

    public function getMemberStats(Request $request): JsonResponse
    {
        $team = Team::where('owner_id', auth()->id())->firstOrFail();

        $memberId = $request->input('member_id');
        
        if (!$team->members()->where('user_id', $memberId)->exists()) {
            return response()->json(['message' => 'العضو غير موجود في فريقك'], 404);
        }

        $cacheKey = "member_stats_{$team->id}_{$memberId}";
        
        $memberStats = Cache::remember($cacheKey, 300, function () use ($team, $memberId) {
            $tasks = \App\Models\Task::where('team_id', $team->id)
                ->where('assigned_to', $memberId)
                ->select(['id', 'title', 'status', 'progress', 'reward_amount'])
                ->get();
            
            $totalTasks = $tasks->count();
            $completedTasks = $tasks->where('status', 'completed')->count();
            $averageProgress = $totalTasks > 0 ? $tasks->avg('progress') : 0;
            
            return [
                'tasks' => $tasks->map(function ($task) {
                    return [
                        'id' => $task->id,
                        'title' => $task->title,
                        'status' => $task->status,
                        'progress' => $task->progress,
                        'reward_amount' => $task->reward_amount,
                    ];
                }),
                'stats' => [
                    'total_tasks' => $totalTasks,
                    'completed_tasks' => $completedTasks,
                    'pending_tasks' => $tasks->where('status', 'pending')->count(),
                    'in_progress_tasks' => $tasks->where('status', 'in_progress')->count(),
                    'average_progress' => round($averageProgress, 1),
                    'completion_rate' => $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 1) : 0,
                ]
            ];
        });
        
        return response()->json([
            'message' => 'تم جلب إحصائيات العضو بنجاح',
            'data' => $memberStats
        ])->setMaxAge(300)->setPublic();
    }
}
