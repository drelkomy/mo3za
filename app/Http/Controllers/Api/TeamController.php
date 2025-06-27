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
        
        // مسح كاش المكافآت لجميع الحالات الممكنة
        $statuses = ['', 'pending', 'delivered', 'cancelled'];
        $perPages = [10, 20, 30, 40, 50];
        for ($i = 1; $i <= 5; $i++) {
            foreach ($statuses as $status) {
                foreach ($perPages as $perPage) {
                    Cache::forget("team_rewards_{$team->id}_page_{$i}_per_{$perPage}_status_{$status}");
                }
            }
        }
        
        // مسح كاش إحصائيات الأعضاء
        $memberIds = $team->members()->pluck('user_id')->push($team->owner_id)->toArray();
        foreach ($memberIds as $memberId) {
            Cache::forget("my_team_" . $memberId);
            Cache::forget("user_team_" . $memberId);
            
            // مسح كاش إحصائيات مهام العضو
            $this->clearMemberTasksCache($team->id, $memberId);
            
            // مسح كاش مهام العضو
            Cache::forget("my_tasks_" . $memberId);
        }
    }
    
    /**
     * مسح كاش مهام عضو محدد
     */
    private function clearMemberTasksCache($teamId, $memberId): void
    {
        // حذف الكاش لعدة صفحات مثلاً أول 3 صفحات الأكثر عرضًا
        foreach (range(1, 3) as $page) {
            foreach ([10, 20, 50] as $perPage) {
                foreach (['pending', 'in_progress', 'completed', ''] as $status) {
                    $statusKey = $status ?: 'all';
                    Cache::forget("member_task_stats_{$teamId}_{$memberId}_page_{$page}_per_{$perPage}_status_{$statusKey}");
                }
            }
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
        $this->clearMemberTasksCache($teamId, $receiverId);
        
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
                    $query->select('id', 'team_id', 'title', 'description', 'status', 'receiver_id', 'progress', 'created_at', 'due_date')
                          ->with(['receiver:id,name', 'creator:id,name', 'stages:id,task_id,title,status'])
                          ->orderBy('created_at', 'desc');
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
        $withStages = $request->boolean('with_stages', true);
        $withCounts = $request->boolean('with_counts', true);
        $searchQuery = $request->input('q');
        
        $cacheKey = "team_tasks_{$team->id}_page_{$page}_per_{$perPage}_status_{$status}_stages_{$withStages}_counts_{$withCounts}_q_{$searchQuery}";
        
        $tasksData = Cache::remember($cacheKey, 300, function () use ($team, $page, $perPage, $status, $withStages, $withCounts, $searchQuery) {
            $baseQuery = Task::where('team_id', $team->id)
                ->select('id', 'title', 'description', 'status', 'progress', 'receiver_id', 'creator_id', 'team_id', 'created_at', 'due_date');
            
            // تحميل العلاقات بشكل انتقائي
            $relations = [
                'receiver:id,name,email,avatar_url',
                'creator:id,name,email'
            ];
            
            if ($withStages) {
                $relations[] = 'stages:id,task_id,title,status,stage_number';
            }
            
            $baseQuery->with($relations)->withCount('stages');
            
            if ($status) {
                $baseQuery->where('status', $status);
            }
            
            if ($searchQuery) {
                $baseQuery->where('title', 'like', "%{$searchQuery}%");
            }
            
            $total = (clone $baseQuery)->count();
            $tasks = $baseQuery->orderBy('created_at', 'desc')
                ->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();
            
            $statusCounts = [];
            if ($withCounts) {
                $statusCounts = Task::where('team_id', $team->id)
                    ->select('status', DB::raw('count(*) as count'))
                    ->groupBy('status')
                    ->pluck('count', 'status')
                    ->toArray();
            }
            
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
                'data' => TaskResource::collection(collect()),
                'meta' => [
                    'total' => 0,
                    'status_counts' => $tasksData['status_counts'],
                    'current_page' => $tasksData['current_page'],
                    'per_page' => $tasksData['per_page'],
                    'last_page' => $tasksData['last_page']
                ]
            ])->setMaxAge(300)->setPublic();
        }

        return response()->json([
            'message' => 'تم جلب مهام الفريق بنجاح',
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
        
        $userId = auth()->id();
        $key = "team_rewards_{$team->id}_{$userId}";
        $maxAttempts = 60; // الحد الأقصى للمحاولات في الدقيقة
        $decaySeconds = 60; // فترة التحلل بالثواني

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return response()->json([
                'message' => 'تم تجاوز الحد الأقصى لعدد الطلبات. حاول مرة أخرى لاحقًا.',
            ], 429);
        }

        RateLimiter::hit($key, $decaySeconds);
        
        $page = $request->input('page', 1);
        $perPage = min($request->input('per_page', 10), 50);
        $status = $request->input('status');
        $withStats = $request->boolean('with_stats', true);
        
        $cacheKey = "team_rewards_{$team->id}_page_{$page}_per_{$perPage}_status_{$status}_stats_{$withStats}";
        
        $rewardsData = Cache::remember($cacheKey, 300, function () use ($team, $page, $perPage, $status, $withStats) {
            // الحصول على معرفات أعضاء الفريق
            $teamMemberIds = $team->members()->pluck('user_id')->push($team->owner_id)->toArray();
            
            $baseQuery = Reward::where('giver_id', auth()->id())
                ->whereIn('receiver_id', $teamMemberIds)
                ->with([
                    'receiver:id,name,email,avatar_url',
                    'task:id,title,team_id,reward_type,reward_description',
                    'giver:id,name,email,avatar_url'
                ])
                ->select('id', 'amount', 'notes', 'status', 'receiver_id', 'giver_id', 'task_id', 'reward_type', 'reward_description', 'created_at');
            
            if ($status) {
                $baseQuery->where('status', $status);
            }
            
            $total = (clone $baseQuery)->count();
            $rewards = $baseQuery->orderBy('created_at', 'desc')
                ->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();
            
            $totalAmount = 0;
            $userStats = [];
            $statusCounts = [];
            
            if ($withStats && $total > 0) {
                $totalAmount = (clone $baseQuery)->sum('amount');
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
            }
            
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
                'data' => TeamRewardResource::collection(collect()),
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
    public function createTask(CreateTaskRequest $request, \App\Services\SubscriptionService $subscriptionService): JsonResponse
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
        
        // التحقق من إمكانية إنشاء مهمة جديدة (الاشتراك)
        $user = auth()->user();
        if (!$user->canAddTasks()) {
            return response()->json([
                'message' => 'لا يمكن إنشاء مهام جديدة. يرجى تجديد الاشتراك.',
                'data' => null
            ], 403);
        }
        // ملاحظة: في حالة انتهاء الاشتراك، يجب تحديث حالة المهام الحالية إلى "منتهي" 
        // يمكن تنفيذ هذا المنطق في مكان آخر مثل SubscriptionService أو مراقب الاشتراكات
        
        try {
            // بدء معاملة قاعدة البيانات
            \Illuminate\Support\Facades\DB::beginTransaction();
            
            // إذا كانت المهمة متعددة، قم بإنشاء مهمة منفصلة لكل شخص
            $receiverIds = [$receiverId];
            if ($request->input('is_multiple') && !empty($request->input('selected_members'))) {
                $selectedMembers = is_string($request->input('selected_members')) 
                    ? json_decode($request->input('selected_members'), true) 
                    : $request->input('selected_members');
                
                if (is_array($selectedMembers)) {
                    foreach ($selectedMembers as $memberId) {
                        // تحقق من أن العضو موجود في الفريق ولم يتم إضافته بالفعل
                        $isMember = $team->members()->where('user_id', $memberId)->exists() || $team->owner_id == $memberId;
                        if ($isMember && !in_array($memberId, $receiverIds)) {
                            $receiverIds[] = $memberId;
                        }
                    }
                }
            }

            $tasks = [];
            foreach ($receiverIds as $rid) {
                // إنشاء المهمة مع تخزين حالة الاشتراك بناءً على التحقق المباشر دون الاعتماد على الكاش
                $subscriptionStatus = $user->canAddTasks() ? 'active' : 'expired'; // تحديد حالة الاشتراك بناءً على التحقق المباشر
                $task = Task::create([
                    'title' => $request->input('title'),
                    'description' => $request->input('description'),
                    'receiver_id' => $rid,
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
                    'subscription_status' => $subscriptionStatus, // تخزين حالة الاشتراك مع المهمة بناءً على التحقق المباشر
                ]);
                
                // تحديث عدد المهام في الاشتراك
                $subscriptionService->incrementTasksCreated($user);
                
                // إنشاء مراحل المهمة تلقائيًا وفق عدد المراحل والمدة الزمنية
                $totalStages = $request->input('total_stages', 3);
                $dueDate = $request->input('due_date') ? \Carbon\Carbon::parse($request->input('due_date')) : \Carbon\Carbon::now()->addDays(7);
                $startDate = \Carbon\Carbon::now(); // تاريخ البداية هو تاريخ اليوم تلقائيًا
                $durationDays = $startDate->diffInDays($dueDate);
                $stageDuration = $durationDays > 0 ? floor($durationDays / $totalStages) : 0;
                $remainingDays = $durationDays > 0 ? $durationDays % $totalStages : 0;

                if ($request->has('stages')) {
                    $stages = $request->input('stages');
                    foreach ($stages as $index => $stageData) {
                        $stageStartDate = $startDate->copy()->addDays($index * $stageDuration);
                        $stageEndDate = $startDate->copy()->addDays(($index + 1) * $stageDuration - 1);
                        if ($index == $totalStages - 1) {
                            $stageEndDate = $dueDate->copy();
                        }
                        if ($remainingDays > 0 && $index < $remainingDays) {
                            $stageEndDate->addDay();
                        }
                        TaskStage::create([
                            'task_id' => $task->id,
                            'title' => $stageData['title'],
                            'description' => $stageData['description'] ?? null,
                            'stage_number' => $index + 1,
                            'status' => 'pending',
                            'start_date' => $stageStartDate,
                            'end_date' => $stageEndDate,
                        ]);
                    }
                } else {
                    // إنشاء مراحل تلقائية بناءً على المدة الزمنية
                    for ($i = 1; $i <= $totalStages; $i++) {
                        $stageStartDate = $startDate->copy()->addDays(($i - 1) * $stageDuration);
                        $stageEndDate = $startDate->copy()->addDays($i * $stageDuration - 1);
                        if ($i == $totalStages) {
                            $stageEndDate = $dueDate->copy(); // ضمان أن تاريخ نهاية آخر مرحلة هو تاريخ انتهاء المهمة
                        } elseif ($remainingDays > 0 && $i <= $remainingDays) {
                            $stageEndDate->addDay(); // توزيع الأيام المتبقية على المراحل الأولى
                        }
                        TaskStage::create([
                            'task_id' => $task->id,
                            'title' => "المرحلة $i",
                            'description' => "المرحلة $i من المهمة",
                            'stage_number' => $i,
                            'status' => 'pending',
                            'start_date' => $stageStartDate,
                            'end_date' => $stageEndDate,
                        ]);
                    }
                }
                
                
                $this->clearTaskCache($task->id, $team->id, $rid);
                
                // تحميل العلاقات
                $task->load(['receiver:id,name,email', 'creator:id,name,email', 'stages']);
                
                $tasks[] = $task;
            }
            
            // إتمام المعاملة
            \Illuminate\Support\Facades\DB::commit();
            
            return response()->json([
                'message' => 'تم إنشاء المهام بنجاح لجميع المستلمين',
                'data' => TaskResource::collection($tasks)
            ], 201);
            
        } catch (\Exception $e) {
            // التراجع عن المعاملة في حالة حدوث خطأ
            \Illuminate\Support\Facades\DB::rollBack();
            
            // تسجيل الخطأ
            \Illuminate\Support\Facades\Log::error('Error creating task: ' . $e->getMessage(), [
                'user_id' => auth()->id(),
                'team_id' => $team->id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'message' => 'حدث خطأ أثناء إنشاء المهمة',
                'error' => $e->getMessage()
            ], 500);
        }
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
        // جلب جميع المهام للعضو (بدون فلترة team_id)
        $memberTasks = Task::where('receiver_id', $member->id)->get();
        
        $totalTasks = $memberTasks->count();
        $completedTasks = $memberTasks->where('status', 'completed')->count();
        $inProgressTasks = $memberTasks->where('status', 'in_progress')->count();
        $pendingTasks = $memberTasks->where('status', 'pending')->count();
        
        $averageProgress = $totalTasks > 0 ? round($memberTasks->avg('progress'), 1) : 0;

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
     * حساب معدل الإنجاز
     */
    private function calculateCompletionRate($tasks): string
    {
        if (empty($tasks) || count($tasks) == 0) return '0%';
        
        $completed = 0;
        foreach ($tasks as $task) {
            if ($task->status === 'completed') {
                $completed++;
            }
        }
        
        $rate = round(($completed / count($tasks)) * 100, 2);
        return $rate . '%';
    }
    
    /**
     * إحصائيات مهام أعضاء الفريق
     */
    public function teamMembersTaskStats(Request $request, Team $team): JsonResponse
    {
        // التحقق من الصلاحيات
        if (!$team->isMember(auth()->id())) {
            return response()->json(['message' => 'غير مصرح لك بالوصول إلى إحصائيات هذا الفريق'], 403);
        }
        
        $page = $request->input('page', 1);
        $perPage = min($request->input('per_page', 10), 50);
        $status = $request->input('status');
        
        $cacheKey = "team_members_task_stats_{$team->id}_page_{$page}_per_{$perPage}_status_{$status}";
        
        $statsData = Cache::remember($cacheKey, 300, function () use ($team, $page, $perPage, $status) {
            // جلب أعضاء الفريق مع إحصائيات المهام
            $query = $team->members()
                ->select('users.id', 'users.name', 'users.email')
                ->withCount([
                    'receivedTasks as total_tasks',
                    'receivedTasks as pending_tasks' => function ($q) {
                        $q->where('status', 'pending');
                    },
                    'receivedTasks as in_progress_tasks' => function ($q) {
                        $q->where('status', 'in_progress');
                    },
                    'receivedTasks as completed_tasks' => function ($q) {
                        $q->where('status', 'completed');
                    }
                ]);
            
            if ($status) {
                $query->withCount([
                    'receivedTasks as filtered_tasks' => function ($q) use ($status) {
                        $q->where('status', $status);
                    }
                ]);
            }
            
            $total = $team->members()->count();
            $currentPage = $page;
            $lastPage = ceil($total / $perPage);
            
            $members = $query->offset(($currentPage - 1) * $perPage)
                ->limit($perPage)
                ->get();
            
            return [
                'members' => $members,
                'total' => $total,
                'current_page' => $currentPage,
                'per_page' => $perPage,
                'last_page' => $lastPage
            ];
        });
        
        return response()->json([
            'message' => 'تم جلب إحصائيات مهام أعضاء الفريق بنجاح',
            'data' => $statsData['members']->map(function ($member) use ($status) {
                return [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'total_tasks' => $member->total_tasks,
                    'pending_tasks' => $member->pending_tasks,
                    'in_progress_tasks' => $member->in_progress_tasks,
                    'completed_tasks' => $member->completed_tasks,
                    'filtered_tasks' => $status ? $member->filtered_tasks : null
                ];
            }),
            'meta' => [
                'total' => $statsData['total'],
                'current_page' => $statsData['current_page'],
                'per_page' => $statsData['per_page'],
                'last_page' => $statsData['last_page']
            ]
        ])->setMaxAge(300)->setPublic();
    }
    
    /**
     * عرض مكافآت الفريق حتى لو انتهى الاشتراك
     */
    public function teamRewards(Request $request, Team $team): JsonResponse
    {
        // التحقق من المصادقة
        if (!auth()->check()) {
            return response()->json(['message' => 'غير مصادق عليك'], 401);
        }
        
        // التحقق من الصلاحيات
        if (!$team->isMember(auth()->id())) {
            return response()->json(['message' => 'غير مصرح لك بالوصول إلى مكافآت هذا الفريق'], 403);
        }
        
        $userId = auth()->id();
        $key = "team_rewards_all_{$team->id}_{$userId}";
        $maxAttempts = 60; // الحد الأقصى للمحاولات في الدقيقة
        $decaySeconds = 60; // فترة التحلل بالثواني

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return response()->json([
                'message' => 'تم تجاوز الحد الأقصى لعدد الطلبات. حاول مرة أخرى لاحقًا.',
            ], 429);
        }

        RateLimiter::hit($key, $decaySeconds);
        
        $page = $request->input('page', 1);
        $perPage = min($request->input('per_page', 10), 50);
        $status = $request->input('status');
        $withStats = $request->boolean('with_stats', true);
        
        $cacheKey = "team_rewards_{$team->id}_page_{$page}_per_{$perPage}_status_{$status}_stats_{$withStats}";
        
        $rewardsData = Cache::remember($cacheKey, 300, function () use ($team, $page, $perPage, $status, $withStats) {
            $baseQuery = Reward::whereIn('receiver_id', $team->members()->pluck('user_id'))
                ->with([
                    'task:id,title',
                    'receiver:id,name'
                ]);
            
            if ($status) {
                $baseQuery->where('status', $status);
            }
            
            $total = (clone $baseQuery)->count();
            $rewards = $baseQuery->offset(($page - 1) * $perPage)
                ->limit($perPage)
                ->orderBy('created_at', 'desc')
                ->get();
            
            $totalAmount = 0;
            $statusCounts = [];
            
            if ($withStats && $total > 0) {
                $totalAmount = (clone $baseQuery)->sum('amount');
                $statusCounts = DB::table('rewards')
                    ->select([
                        DB::raw('SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending'),
                        DB::raw('SUM(CASE WHEN status = "received" THEN 1 ELSE 0 END) as received')
                    ])
                    ->whereIn('receiver_id', $team->members()->pluck('user_id'))
                    ->when($status, fn($q) => $q->where('status', $status))
                    ->first();
            }
            
            return [
                'rewards' => $rewards,
                'total' => $total,
                'total_amount' => $totalAmount,
                'status_counts' => [
                    'pending' => $statusCounts->pending ?? 0,
                    'received' => $statusCounts->received ?? 0
                ],
                'current_page' => $page,
                'per_page' => $perPage,
                'last_page' => ceil($total / $perPage)
            ];
        });
        
        if ($rewardsData['total'] == 0) {
            return response()->json([
                'message' => 'لا توجد مكافآت مرتبطة بأعضاء الفريق حاليًا',
                'data' => \App\Http\Resources\RewardResource::collection(collect()),
                'meta' => [
                    'total' => 0,
                    'total_amount' => 0,
                    'status_counts' => [],
                    'current_page' => $rewardsData['current_page'],
                    'per_page' => $rewardsData['per_page'],
                    'last_page' => $rewardsData['last_page']
                ]
            ])->setMaxAge(300)->setPublic();
        }

        return response()->json([
            'message' => 'تم جلب مكافآت الفريق بنجاح',
            'data' => \App\Http\Resources\RewardResource::collection($rewardsData['rewards']),
            'meta' => [
                'total' => $rewardsData['total'],
                'total_amount' => $rewardsData['total_amount'],
                'status_counts' => $rewardsData['status_counts'],
                'current_page' => $rewardsData['current_page'],
                'per_page' => $rewardsData['per_page'],
                'last_page' => $rewardsData['last_page']
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

        $userId = auth()->id();
        $key = "member_tasks_{$team->id}_{$memberId}_{$userId}";
        $maxAttempts = 60; // الحد الأقصى للمحاولات في الدقيقة
        $decaySeconds = 60; // فترة التحلل بالثواني

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return response()->json([
                'message' => 'تم تجاوز الحد الأقصى لعدد الطلبات. حاول مرة أخرى لاحقًا.',
            ], 429);
        }

        RateLimiter::hit($key, $decaySeconds);

        $page = $request->input('page', 1);
        $perPage = min($request->input('per_page', 10), 50);
        $status = $request->input('status');
        $withStages = $request->boolean('with_stages', true);
        $withCounts = $request->boolean('with_counts', true);
        $searchQuery = $request->input('q');

        $cacheKey = "member_task_stats_{$team->id}_{$memberId}_page_{$page}_per_{$perPage}_status_{$status}_stages_{$withStages}_counts_{$withCounts}_q_{$searchQuery}";

        $tasksData = Cache::remember($cacheKey, 300, function () use ($team, $memberId, $page, $perPage, $status, $withStages, $withCounts, $searchQuery) {
            $baseQuery = Task::where('team_id', $team->id)
                ->where(function ($q) use ($memberId) {
                    $q->where('receiver_id', $memberId)
                      ->orWhereHas('members', function ($query) use ($memberId) {
                          $query->where('user_id', $memberId);
                      });
                })
                ->select('id', 'title', 'description', 'status', 'progress', 'receiver_id', 'creator_id', 'team_id', 'created_at', 'due_date');

            // تحميل العلاقات بشكل انتقائي
            $relations = [
                'receiver:id,name,email,avatar_url',
                'creator:id,name,email'
            ];
            
            if ($withStages) {
                $relations[] = 'stages:id,task_id,title,status,stage_number';
            }
            
            $baseQuery->with($relations)->withCount('stages');

            if ($status) {
                $baseQuery->where('status', $status);
            }

            if ($searchQuery) {
                $baseQuery->where('title', 'like', "%{$searchQuery}%");
            }

            $total = (clone $baseQuery)->count();
            $tasks = $baseQuery->orderBy('created_at', 'desc')
                ->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();

            $statusCounts = [];
            if ($withCounts && $total > 0) {
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
            }

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
                'data' => TaskResource::collection(collect()),
                'meta' => [
                    'total' => 0,
                    'status_counts' => $tasksData['status_counts'],
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
