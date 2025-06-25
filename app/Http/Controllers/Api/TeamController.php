<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CreateTeamRequest;
use App\Http\Requests\Api\CreateTaskRequest;
use App\Http\Requests\Api\RemoveMemberRequest;
use App\Http\Requests\Api\UpdateTeamNameRequest;
use App\Http\Resources\TeamDetailResource;
use App\Http\Resources\TaskResource;
use App\Http\Resources\RewardResource;
use App\Http\Resources\TeamRewardResource;
use App\Models\Team;
use App\Models\Task;
use App\Models\TaskStage;
use App\Models\Reward;
use App\Models\User;
use App\Models\TeamMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

class TeamController extends Controller
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
        for ($i = 1; $i <= 10; $i++) { // زيادة عدد الصفحات المحذوفة
            Cache::forget("team_tasks_{$team->id}_page_{$i}_per_10_status_");
            Cache::forget("team_tasks_{$team->id}_page_{$i}_per_10_status_completed");
            Cache::forget("team_tasks_{$team->id}_page_{$i}_per_10_status_pending");
            Cache::forget("team_tasks_{$team->id}_page_{$i}_per_10_status_in_progress");
            
            // مسح كاش الإحصائيات الجديد
            Cache::forget("team_members_task_stats_{$team->id}_page_{$i}_tasks_per_page_10");
        }
        
        // مسح كاش المكافآت
        for ($i = 1; $i <= 5; $i++) {
            Cache::forget("team_rewards_{$team->id}_page_{$i}_per_10_status_");
        }
        
        // مسح كاش إحصائيات الأعضاء
        $memberIds = $team->members()->pluck('user_id')->push($team->owner_id)->toArray();
        foreach ($memberIds as $memberId) {
            Cache::forget("my_team_" . $memberId);
            Cache::forget("user_team_" . $memberId);
            
            // مسح كاش إحصائيات مهام العضو
            for ($i = 1; $i <= 5; $i++) {
                Cache::forget("member_task_stats_{$team->id}_{$memberId}_page_{$i}");
            }
            
            // مسح كاش مهام العضو
            Cache::forget("my_tasks_" . $memberId);
        }
    }
    
    /**
     * مسح كاش المهمة
     */
    private function clearTaskCache(int $taskId, int $teamId, int $receiverId): void
    {
        // مسح كاش المهام
        for ($i = 1; $i <= 5; $i++) {
            Cache::forget("team_tasks_{$teamId}_page_{$i}_per_10_status_");
            Cache::forget("team_tasks_{$teamId}_page_{$i}_per_10_status_completed");
            Cache::forget("team_tasks_{$teamId}_page_{$i}_per_10_status_pending");
            Cache::forget("team_tasks_{$teamId}_page_{$i}_per_10_status_in_progress");
        }
        
        // مسح كاش مهام المستلم
        Cache::forget("my_tasks_" . $receiverId);
        
        // مسح كاش إحصائيات المستلم
        for ($i = 1; $i <= 3; $i++) {
            Cache::forget("member_task_stats_{$teamId}_{$receiverId}_page_{$i}");
        }
        
        // مسح كاش إحصائيات الفريق
        for ($i = 1; $i <= 3; $i++) {
            Cache::forget("team_members_task_stats_{$teamId}_page_{$i}_per_10");
        }
    }
    public function create(CreateTeamRequest $request): JsonResponse
    {
        $team = Team::create([
            'name' => $request->input('name'),
            'owner_id' => auth()->id()
        ]);
        
        $team->load('owner');
        
        return response()->json([
            'message' => 'تم إنشاء الفريق بنجاح',
            'team' => new TeamDetailResource($team)
        ], 201);
    }
    
    public function myTeam(\App\Http\Requests\Api\MyTeamRequest $request): JsonResponse
    {
        $team = Team::where('owner_id', auth()->id())
            ->with([
                'owner:id,name,email',
                'members:id,name,email,avatar_url',
                'tasks' => function($query) {
                    $query->select('id', 'team_id', 'title', 'status', 'receiver_id', 'progress')
                          ->with('receiver:id,name')
                          ->orderBy('created_at', 'desc')
                          ->take(10);
                }
            ])
            ->select('id', 'name', 'owner_id', 'created_at')
            ->first();
            
        if (!$team) {
            return response()->json([
                'message' => 'لا تملك فريقاً',
                'data' => [
                    'is_owner' => false
                ]
            ]);
        }
        
        return response()->json([
            'message' => 'تم جلب فريقك بنجاح',
            'data' => new \App\Http\Resources\MyTeamResource($team)
        ])->header('Cache-Control', 'no-cache, no-store, must-revalidate')
         ->header('Pragma', 'no-cache')
         ->header('Expires', '0');
    }
    
    public function updateName(UpdateTeamNameRequest $request): JsonResponse
    {
        $team = Team::where('owner_id', auth()->id())->firstOrFail();
        
        $team->update(['name' => $request->input('name')]);
        
        // مسح الكاش المتعلق بالفريق
        $this->clearTeamCache($team);
        
        $team = Team::where('id', $team->id)->with('owner:id,name,email,avatar_url')->first();
        
        return response()->json([
            'message' => 'تم تعديل اسم الفريق بنجاح',
            'team' => new TeamDetailResource($team)
        ]);
    }
    
    public function removeMember(RemoveMemberRequest $request): JsonResponse
    {
        $team = Team::with('members')->where('owner_id', auth()->id())->firstOrFail();
        
        $memberId = $request->input('member_id');
        
        if (!$team->members->contains('id', $memberId)) {
            return response()->json([
                'message' => 'العضو غير موجود في فريقك'
            ], 404);
        }
        
        $team->members()->detach($memberId);
        
        // مسح الكاش المتعلق بالفريق والعضو
        $this->clearTeamCache($team);
        
        // مسح كاش العضو المحذوف بشكل خاص
        Cache::forget("my_team_" . $memberId);
        Cache::forget("user_team_" . $memberId);
        
        return response()->json([
            'message' => 'تم إزالة العضو من فريقك بنجاح'
        ]);
    }
    
    /**
     * عرض مهام الفريق
     */
    public function getTeamTasks(\App\Http\Requests\Api\TeamTasksRequest $request): JsonResponse
    {
        // التحقق من وجود فريق للمستخدم
        $team = $this->getUserTeam();
        if (!$team) {
            return response()->json([
                'message' => 'لا تملك فريقاً أو لست عضوًا في أي فريق. يرجى إنشاء فريق أو الانضمام إلى فريق لعرض المهام.',
                'data' => null
            ], 404);
        }
        
        $page = $request->input('page', 1);
        $perPage = min($request->input('per_page', 10), 50);
        $status = $request->input('status');
        
        $cacheKey = "team_tasks_{$team->id}_page_{$page}_per_{$perPage}_status_{$status}";
        
        $tasksData = Cache::remember($cacheKey, 300, function () use ($team, $page, $perPage, $status) {
            $query = Task::where('team_id', $team->id)
                ->with([
                    'receiver:id,name,email,avatar_url',
                    'creator:id,name,email',
                    'stages' => function($q) {
                        $q->orderBy('stage_number');
                    }
                ])
                ->select('id', 'title', 'description', 'status', 'progress', 'receiver_id', 'creator_id', 'team_id', 'created_at', 'due_date')
                ->withCount('stages');
            
            if ($status) {
                $query->where('status', $status);
            }
            
            $tasks = $query->orderBy('created_at', 'desc')
                ->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();
            
            $total = Task::where('team_id', $team->id)
                ->when($status, fn($q) => $q->where('status', $status))
                ->count();
                
            $statusCounts = Task::where('team_id', $team->id)
                ->select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray();
            
            return [
                'tasks' => $tasks,
                'total' => $total,
                'status_counts' => $statusCounts,
                'current_page' => $page,
                'per_page' => $perPage,
                'last_page' => ceil($total / $perPage)
            ];
        });
        
        if ($tasksData['total'] == 0) {
            return response()->json([
                'message' => 'لا توجد مهام مرتبطة بفريقك حاليًا',
                'data' => [],
                'meta' => [
                    'total' => 0,
                    'status_counts' => [],
                    'current_page' => $tasksData['current_page'],
                    'per_page' => $tasksData['per_page'],
                    'last_page' => $tasksData['last_page']
                ]
            ])->setMaxAge(300)->setPublic();
        }

        return response()->json([
            'message' => 'تم جلب مهام الفريق بنجاح',
            'data' => TeamTaskResource::collection($tasksData['tasks']),
            'meta' => [
                'total' => $tasksData['total'],
                'status_counts' => $tasksData['status_counts'],
                'current_page' => $tasksData['current_page'],
                'per_page' => $tasksData['per_page'],
                'last_page' => $tasksData['last_page']
            ]
        ])->setMaxAge(300)->setPublic();
    }
    
    /**
     * عرض مكافآت الفريق
     */
    public function getTeamRewards(\App\Http\Requests\Api\TeamRewardsRequest $request): JsonResponse
    {
        // التحقق من وجود فريق للمستخدم (سواء كان مالكاً أو عضواً)
        $team = $this->getUserTeam();
        if (!$team) {
            return response()->json([
                'message' => 'لا تملك فريقاً',
                'data' => null
            ], 404);
        }
        
        $page = $request->input('page', 1);
        $perPage = min($request->input('per_page', 10), 50);
        $status = $request->input('status');
        
        $cacheKey = "team_rewards_{$team->id}_page_{$page}_per_{$perPage}_status_{$status}";
        
        $rewardsData = Cache::remember($cacheKey, 300, function () use ($team, $page, $perPage, $status) {
            // الحصول على معرفات أعضاء الفريق
            $teamMemberIds = $team->members()->pluck('user_id')->push($team->owner_id)->toArray();
            
            $query = Reward::where('giver_id', auth()->id())
                ->whereIn('receiver_id', $teamMemberIds)
                ->with([
                    'receiver:id,name,email,avatar_url',
                    'task:id,title,team_id,reward_type,reward_description',
                    'giver:id,name,email,avatar_url'
                ])
                ->select('id', 'amount', 'notes', 'status', 'receiver_id', 'giver_id', 'task_id', 'reward_type', 'reward_description', 'created_at');
            
            if ($status) {
                $query->where('status', $status);
            }
            
            $rewards = $query->orderBy('created_at', 'desc')
                ->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();
            
            $total = Reward::where('giver_id', auth()->id())
                ->whereIn('receiver_id', $teamMemberIds)
                ->when($status, fn($q) => $q->where('status', $status))
                ->count();
                
            $totalAmount = Reward::where('giver_id', auth()->id())
                ->whereIn('receiver_id', $teamMemberIds)
                ->when($status, fn($q) => $q->where('status', $status))
                ->sum('amount');
                
            $userStats = Reward::where('giver_id', auth()->id())
                ->whereIn('receiver_id', $teamMemberIds)
                ->select('receiver_id', DB::raw('SUM(amount) as total_amount'), DB::raw('COUNT(*) as count'))
                ->groupBy('receiver_id')
                ->get()
                ->keyBy('receiver_id')
                ->toArray();
                
            $statusCounts = Reward::where('giver_id', auth()->id())
                ->whereIn('receiver_id', $teamMemberIds)
                ->select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray();
            
            return [
                'rewards' => $rewards,
                'total' => $total,
                'total_amount' => $totalAmount,
                'user_stats' => $userStats,
                'status_counts' => $statusCounts,
                'current_page' => $page,
                'per_page' => $perPage,
                'last_page' => ceil($total / $perPage)
            ];
        });
        
        if ($rewardsData['total'] == 0) {
            return response()->json([
                'message' => 'لم تقم بتوزيع أي مكافآت لأعضاء الفريق',
                'data' => [],
                'meta' => [
                    'total' => 0,
                    'total_amount' => 0,
                    'user_stats' => [],
                    'status_counts' => [],
                    'current_page' => $rewardsData['current_page'],
                    'per_page' => $rewardsData['per_page'],
                    'last_page' => $rewardsData['last_page']
                ]
            ])->setMaxAge(300)->setPublic();
        }
        
        return response()->json([
            'message' => 'تم جلب مكافآت الفريق بنجاح',
            'data' => TeamRewardResource::collection($rewardsData['rewards']),
            'meta' => [
                'total' => $rewardsData['total'],
                'total_amount' => $rewardsData['total_amount'],
                'user_stats' => $rewardsData['user_stats'],
                'status_counts' => $rewardsData['status_counts'] ?? [],
                'current_page' => $rewardsData['current_page'],
                'per_page' => $rewardsData['per_page'],
                'last_page' => $rewardsData['last_page']
            ]
        ])->setMaxAge(300)->setPublic();
    }
    
    /**
     * إنشاء مهمة جديدة للفريق
     */
    public function createTask(CreateTaskRequest $request): JsonResponse
    {
        // التحقق من وجود فريق للمستخدم
        $team = $this->getUserTeam();
        if (!$team) {
            return response()->json([
                'message' => 'لا تملك فريقاً',
                'data' => null
            ], 404);
        }
        
        // التحقق من أن المستلم عضو في الفريق
        $receiverId = $request->input('receiver_id');
        $isMember = $team->members()->where('user_id', $receiverId)->exists() || $team->owner_id == $receiverId;
        
        if (!$isMember) {
            return response()->json([
                'message' => 'المستلم ليس عضواً في الفريق',
                'data' => null
            ], 400);
        }
        
        // إنشاء المهمة
        $task = Task::create([
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'receiver_id' => $receiverId,
            'creator_id' => auth()->id(),
            'team_id' => $team->id,
            'status' => 'pending',
            'progress' => 0,
            'priority' => $request->input('priority', 'normal'),
            'due_date' => $request->input('due_date'),
            'total_stages' => $request->input('total_stages', 3),
            'reward_amount' => $request->input('reward_amount'),
            'reward_type' => $request->input('reward_type', 'cash'),
            'reward_description' => $request->input('reward_description'),
            'is_multiple' => $request->input('is_multiple', false),
            'selected_members' => $request->input('is_multiple') ? $request->input('selected_members', []) : null,
        ]);
        
        // إنشاء مراحل المهمة
        if ($request->has('stages')) {
            $stages = $request->input('stages');
            foreach ($stages as $index => $stageData) {
                TaskStage::create([
                    'task_id' => $task->id,
                    'title' => $stageData['title'],
                    'description' => $stageData['description'] ?? null,
                    'stage_number' => $index + 1,
                    'status' => 'pending',
                ]);
            }
        } else {
            // إنشاء مراحل افتراضية
            $defaultStages = [
                ['title' => 'البداية', 'description' => 'مرحلة البدء في المهمة'],
                ['title' => 'التنفيذ', 'description' => 'مرحلة تنفيذ المهمة'],
                ['title' => 'الإنهاء', 'description' => 'مرحلة إنهاء المهمة']
            ];
            
            foreach ($defaultStages as $index => $stageData) {
                TaskStage::create([
                    'task_id' => $task->id,
                    'title' => $stageData['title'],
                    'description' => $stageData['description'],
                    'stage_number' => $index + 1,
                    'status' => 'pending',
                ]);
            }
        }
        
        // مسح الكاش المتعلق بالمهمة
        $this->clearTaskCache($task->id, $team->id, $receiverId);
        
        // إذا كانت المهمة متعددة، قم بإضافة الأعضاء المحددين
        if ($request->input('is_multiple') && !empty($request->input('selected_members'))) {
            $selectedMembers = $request->input('selected_members');
            foreach ($selectedMembers as $memberId) {
                // تحقق من أن العضو موجود في الفريق
                $isMember = $team->members()->where('user_id', $memberId)->exists() || $team->owner_id == $memberId;
                if ($isMember) {
                    $task->members()->attach($memberId, [
                        'is_primary' => ($memberId == $receiverId),
                        'status' => 'pending',
                        'completion_percentage' => 0
                    ]);
                }
            }
        }
        
        // تحميل العلاقات
        $task->load(['receiver:id,name,email', 'creator:id,name,email', 'stages', 'members:id,name,email']);
        
        return response()->json([
            'message' => 'تم إنشاء المهمة بنجاح',
            'data' => new TaskResource($task)
        ], 201);
    }

    /**
     * الحصول على فريق المستخدم (سواء كان مالكاً أو عضواً)
     */
    private function getUserTeam(): ?Team
    {
        $userId = auth()->id();
        $cacheKey = "user_team_{$userId}";
        
        return Cache::remember($cacheKey, 300, function () use ($userId) {
            // البحث عن فريق يملكه المستخدم
            $ownedTeam = Team::where('owner_id', $userId)->first();
            if ($ownedTeam) {
                return $ownedTeam;
            }
            
            // البحث عن فريق ينتمي إليه المستخدم
            $memberTeam = Team::whereHas('members', function($query) use ($userId) {
                $query->where('user_id', $userId);
            })->first();
            
            return $memberTeam;
        });
    }

    /**
     * حساب إحصائيات العضو
     */
    private function calculateMemberStats(Team $team, $member): array
    {
        $totalTasks = Task::where('team_id', $team->id)
            ->where(function ($q) use ($member) {
                $q->where('receiver_id', $member->id)
                  ->orWhereHas('members', function ($query) use ($member) {
                      $query->where('user_id', $member->id);
                  });
            })
            ->count();

        $completedTasks = Task::where('team_id', $team->id)
            ->where('status', 'completed')
            ->where(function ($q) use ($member) {
                $q->where('receiver_id', $member->id)
                  ->orWhereHas('members', function ($query) use ($member) {
                      $query->where('user_id', $member->id);
                  });
            })
            ->count();

        $inProgressTasks = Task::where('team_id', $team->id)
            ->where('status', 'in_progress')
            ->where(function ($q) use ($member) {
                $q->where('receiver_id', $member->id)
                  ->orWhereHas('members', function ($query) use ($member) {
                      $query->where('user_id', $member->id);
                  });
            })
            ->count();

        $pendingTasks = $totalTasks - $completedTasks - $inProgressTasks;

        $averageProgress = $totalTasks > 0 ? 
            round(Task::where('team_id', $team->id)
                ->where(function ($q) use ($member) {
                    $q->where('receiver_id', $member->id)
                      ->orWhereHas('members', function ($query) use ($member) {
                          $query->where('user_id', $member->id);
                      });
                })
                ->avg('progress'), 1) : 0;

        return [
            'total_tasks' => $totalTasks,
            'completed_tasks' => $completedTasks,
            'in_progress_tasks' => $inProgressTasks,
            'pending_tasks' => $pendingTasks,
            'average_progress' => $averageProgress,
            'completion_rate' => $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 1) : 0,
        ];
    }

    /**
     * تنسيق المهمة للعرض
     */
    private function formatTaskForDisplay($task): array
    {
        return [
            'id' => $task->id,
            'title' => $task->title,
            'description' => $task->description ? (strlen($task->description) > 100 ? substr($task->description, 0, 100) . '...' : $task->description) : null,
            'status' => $task->status,
            'progress' => $task->progress,
            'priority' => $task->priority,
            'due_date' => $task->due_date,
            'created_at' => $task->created_at->format('Y-m-d H:i:s'),
            'creator' => $task->creator ? [
                'id' => $task->creator->id,
                'name' => $task->creator->name,
            ] : null,
            'stages_count' => $task->stages_count,
            'completed_stages' => $task->stages ? $task->stages->where('status', 'completed')->count() : 0,
        ];
    }

    /**
     * عرض إحصائيات مهام أعضاء الفريق مع 10 مهام كحد أقصى في الصفحة الواحدة
     * يتم عرض المهام بشكل متتالي - 10 مهام للعضو الأول، ثم 10 للثاني، وهكذا
     */
    public function getMembersTaskStats(Request $request): JsonResponse
    {
        $team = $this->getUserTeam();
        if (!$team) {
            return response()->json([
                'message' => 'لا تملك فريقاً أو لست عضوًا في أي فريق.',
                'data' => null
            ], 404);
        }

        $page = $request->input('page', 1);
        $tasksPerPage = 10; // عدد ثابت من المهام في الصفحة الواحدة
        
        $cacheKey = "team_members_task_stats_{$team->id}_page_{$page}_tasks_per_page_{$tasksPerPage}";

        $statsData = Cache::remember($cacheKey, 300, function () use ($team, $page, $tasksPerPage) {
            // جلب جميع أعضاء الفريق مع تضمين مالك الفريق
            $allMembers = collect();
            
            // إضافة مالك الفريق أولاً
            $owner = User::select('id', 'name', 'email', 'avatar_url')
                ->where('id', $team->owner_id)
                ->first();
            if ($owner) {
                $allMembers->push($owner);
            }
            
            // إضافة باقي الأعضاء
            $members = $team->members()
                ->select('users.id', 'users.name', 'users.email', 'users.avatar_url')
                ->get();
            
            // التأكد من عدم تكرار المالك إذا كان عضواً أيضاً
            $members->each(function($member) use ($allMembers, $owner) {
                if (!$owner || $member->id !== $owner->id) {
                    $allMembers->push($member);
                }
            });

            // جمع جميع المهام من جميع الأعضاء في مجموعة واحدة
            $allTasks = Task::where('team_id', $team->id)
                ->with([
                    'receiver:id,name,email,avatar_url',
                    'creator:id,name,email',
                    'stages' => function($q) {
                        $q->select('id', 'task_id', 'title', 'status', 'stage_number')
                          ->orderBy('stage_number');
                    }
                ])
                ->select('id', 'title', 'description', 'status', 'progress', 'receiver_id', 'creator_id', 'created_at', 'due_date', 'priority', 'team_id')
                ->withCount('stages')
                ->orderBy('created_at', 'desc')
                ->get();

            // حساب إجمالي المهام
            $totalTasks = $allTasks->count();
            $totalPages = $totalTasks > 0 ? ceil($totalTasks / $tasksPerPage) : 1;
            
            // جلب المهام للصفحة المطلوبة
            $paginatedTasks = $allTasks->skip(($page - 1) * $tasksPerPage)->take($tasksPerPage);
            
            // تجميع المهام حسب العضو للصفحة الحالية مع الحد الأقصى للمهام لكل عضو
            $pageMembers = [];
            $currentMemberTasks = [];
            $currentMemberId = null;
            $memberTasksData = [];
            $totalTasksDisplayed = 0;

            // ملء بيانات الأعضاء والإحصائيات
            foreach ($allMembers as $member) {
                $memberStats = $this->calculateMemberStats($team, $member);
                $memberTasksData[$member->id] = [
                    'member' => $member,
                    'stats' => $memberStats
                ];
            }

            foreach ($paginatedTasks as $task) {
                if ($totalTasksDisplayed >= $tasksPerPage) {
                    break;
                }
                
                $memberId = $task->receiver_id;
                
                if ($currentMemberId !== $memberId) {
                    // إنهاء العضو السابق إذا وُجد
                    if ($currentMemberId !== null && !empty($currentMemberTasks)) {
                        $pageMembers[] = [
                            'member_info' => [
                                'id' => $currentMemberId,
                                'name' => $memberTasksData[$currentMemberId]['member']->name,
                                'email' => $memberTasksData[$currentMemberId]['member']->email,
                                'avatar_url' => $memberTasksData[$currentMemberId]['member']->avatar_url,
                                'is_owner' => $currentMemberId == $team->owner_id
                            ],
                            'statistics' => $memberTasksData[$currentMemberId]['stats'],
                            'tasks' => array_map(function($task) {
                                return $this->formatTaskForDisplay($task);
                            }, $currentMemberTasks),
                            'displayed_tasks_count' => count($currentMemberTasks),
                            'total_tasks_count' => $memberTasksData[$currentMemberId]['stats']['total_tasks']
                        ];
                    }
                    
                    // بدء عضو جديد
                    $currentMemberId = $memberId;
                    $currentMemberTasks = [$task];
                } else {
                    if (count($currentMemberTasks) < 10) { // الحد الأقصى للمهام لكل عضو في الصفحة
                        $currentMemberTasks[] = $task;
                    }
                }
                $totalTasksDisplayed++;
            }
            
            // إضافة العضو الأخير
            if ($currentMemberId !== null && !empty($currentMemberTasks)) {
                $pageMembers[] = [
                    'member_info' => [
                        'id' => $currentMemberId,
                        'name' => $memberTasksData[$currentMemberId]['member']->name,
                        'email' => $memberTasksData[$currentMemberId]['member']->email,
                        'avatar_url' => $memberTasksData[$currentMemberId]['member']->avatar_url,
                        'is_owner' => $currentMemberId == $team->owner_id
                    ],
                    'statistics' => $memberTasksData[$currentMemberId]['stats'],
                    'tasks' => array_map(function($task) {
                        return $this->formatTaskForDisplay($task);
                    }, $currentMemberTasks),
                    'displayed_tasks_count' => count($currentMemberTasks),
                    'total_tasks_count' => $memberTasksData[$currentMemberId]['stats']['total_tasks']
                ];
            }

            return [
                'members' => $pageMembers,
                'total_tasks' => $totalTasks,
                'total_members' => $allMembers->count(),
                'current_page' => $page,
                'tasks_per_page' => $tasksPerPage,
                'last_page' => $totalPages,
                'has_more_pages' => $page < $totalPages
            ];
        });

        if ($statsData['total_tasks'] == 0) {
            return response()->json([
                'message' => 'لا توجد مهام في فريقك حاليًا',
                'data' => [],
                'meta' => [
                    'total_tasks' => 0,
                    'total_members' => $statsData['total_members'],
                    'current_page' => $statsData['current_page'],
                    'tasks_per_page' => $statsData['tasks_per_page'],
                    'last_page' => $statsData['last_page'],
                    'has_more_pages' => $statsData['has_more_pages']
                ]
            ])->setMaxAge(300)->setPublic();
        }

        return response()->json([
            'message' => 'تم جلب إحصائيات مهام أعضاء الفريق بنجاح',
            'data' => $statsData['members'],
            'meta' => [
                'total_tasks' => $statsData['total_tasks'],
                'total_members' => $statsData['total_members'],
                'current_page' => $statsData['current_page'],
                'tasks_per_page' => $statsData['tasks_per_page'],
                'last_page' => $statsData['last_page'],
                'has_more_pages' => $statsData['has_more_pages']
            ]
        ])->setMaxAge(300)->setPublic();
    }

    /**
     * عرض مهام عضو معين في الفريق
     */
    public function getMemberTasks(Request $request, $memberId): JsonResponse
    {
        // التحقق من وجود فريق للمستخدم
        $team = $this->getUserTeam();
        if (!$team) {
            return response()->json([
                'message' => 'لا تملك فريقاً أو لست عضوًا في أي فريق.',
                'data' => null
            ], 404);
        }

        // التحقق من أن العضو موجود في الفريق
        $isMember = $team->members()->where('user_id', $memberId)->exists() || $team->owner_id == $memberId;
        if (!$isMember) {
            return response()->json([
                'message' => 'العضو غير موجود في فريقك',
                'data' => null
            ], 404);
        }

        $page = $request->input('page', 1);
        $perPage = min($request->input('per_page', 10), 50);
        $status = $request->input('status');

        $cacheKey = "member_task_stats_{$team->id}_{$memberId}_page_{$page}_per_{$perPage}_status_{$status}";

        $tasksData = Cache::remember($cacheKey, 300, function () use ($team, $memberId, $page, $perPage, $status) {
            $query = Task::where('team_id', $team->id)
                ->where(function ($q) use ($memberId) {
                    $q->where('receiver_id', $memberId)
                      ->orWhereHas('members', function ($query) use ($memberId) {
                          $query->where('user_id', $memberId);
                      });
                })
                ->with([
                    'receiver:id,name,email,avatar_url',
                    'creator:id,name,email',
                    'stages' => function($q) {
                        $q->orderBy('stage_number');
                    }
                ])
                ->select('id', 'title', 'description', 'status', 'progress', 'receiver_id', 'creator_id', 'team_id', 'created_at', 'due_date')
                ->withCount('stages');

            if ($status) {
                $query->where('status', $status);
            }

            $tasks = $query->orderBy('created_at', 'desc')
                ->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();

            $total = Task::where('team_id', $team->id)
                ->where(function ($q) use ($memberId) {
                    $q->where('receiver_id', $memberId)
                      ->orWhereHas('members', function ($query) use ($memberId) {
                          $query->where('user_id', $memberId);
                      });
                })
                ->when($status, fn($q) => $q->where('status', $status))
                ->count();

            $statusCounts = Task::where('team_id', $team->id)
                ->where(function ($q) use ($memberId) {
                    $q->where('receiver_id', $memberId)
                      ->orWhereHas('members', function ($query) use ($memberId) {
                          $query->where('user_id', $memberId);
                      });
                })
                ->select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray();

            return [
                'tasks' => $tasks,
                'total' => $total,
                'status_counts' => $statusCounts,
                'current_page' => $page,
                'per_page' => $perPage,
                'last_page' => ceil($total / $perPage)
            ];
        });

        if ($tasksData['total'] == 0) {
            return response()->json([
                'message' => 'لا توجد مهام مرتبطة بهذا العضو حاليًا',
                'data' => [],
                'meta' => [
                    'total' => 0,
                    'status_counts' => [],
                    'current_page' => $tasksData['current_page'],
                    'per_page' => $tasksData['per_page'],
                    'last_page' => $tasksData['last_page']
                ]
            ])->setMaxAge(300)->setPublic();
        }

        return response()->json([
            'message' => 'تم جلب مهام العضو بنجاح',
            'data' => TaskResource::collection($tasksData['tasks']),
            'meta' => [
                'total' => $tasksData['total'],
                'status_counts' => $tasksData['status_counts'],
                'current_page' => $tasksData['current_page'],
                'per_page' => $tasksData['per_page'],
                'last_page' => $tasksData['last_page']
            ]
        ])->setMaxAge(300)->setPublic();
    }
}
