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
use App\Http\Resources\TeamMemberResource;
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
     * مسح كاش المستخدم الحالي فقط
     */
    private function clearUserCache(int $userId): void
    {
        Cache::forget("my_team_" . $userId);
        Cache::forget("user_team_" . $userId);
        Cache::forget("team_members_" . $userId);
        
        // مسح مفاتيح المهام الأساسية
        foreach (['all', 'pending', 'completed', 'in_progress'] as $status) {
            for ($page = 1; $page <= 3; $page++) {
                foreach ([10, 20, 50] as $perPage) {
                    foreach ([true, false] as $stages) {
                        foreach ([true, false] as $counts) {
                            Cache::forget("team_tasks_{$userId}_page_{$page}_per_{$perPage}_status_{$status}_stages_{$stages}_counts_{$counts}_q_none");
                        }
                    }
                }
            }
        }
    }
    
    /**
     * مسح كاش المهمة
     */
    private function clearTaskCache(int $taskId, int $teamId, int $receiverId): void
    {
        // مسح كاش مهام الفريق
        foreach (['all', 'pending', 'completed', 'in_progress'] as $status) {
            for ($page = 1; $page <= 3; $page++) {
                foreach ([10, 20, 50] as $perPage) {
                    Cache::forget("team_tasks_{$teamId}_page_{$page}_per_{$perPage}_status_{$status}");
                }
            }
        }
        
        // مسح كاش مهام المستلم
        Cache::forget("member_task_stats_{$teamId}_{$receiverId}");
    }
    

    

    public function create(CreateTeamRequest $request): JsonResponse
    {
        $userId = auth()->id();
        
        $team = Team::create([
            'name' => $request->input('name'),
            'owner_id' => $userId
        ]);
        
        $team->load('owner');
        
        // مسح كاش المستخدم
        $this->clearUserCache($userId);
        
        return response()->json([
            'message' => 'تم إنشاء الفريق بنجاح',
            'team' => new TeamDetailResource($team)
        ], 201);
    }
    
    public function myTeam(\App\Http\Requests\Api\MyTeamRequest $request): JsonResponse
    {
        $userId = auth()->id();
        $cacheKey = "my_team_" . $userId;
        
        $teamData = Cache::remember($cacheKey, 300, function () use ($userId) {
            return Team::where('owner_id', $userId)
                ->with([
                    'owner:id,name,email',
                    'members:id,name,email,avatar_url',
                    'tasks' => function($query) use ($userId) {
                        $query->where('creator_id', $userId)
                              ->select('id', 'team_id', 'title', 'description', 'status', 'receiver_id', 'progress', 'created_at', 'due_date')
                              ->with(['receiver:id,name', 'creator:id,name', 'stages'])
                              ->orderBy('created_at', 'desc');
                    }
                ])
                ->select('id', 'name', 'owner_id', 'created_at')
                ->first();
        });
            
        if (!$teamData) {
            return response()->json([
                'message' => 'لا تملك فريقاً',
                'data' => [
                    'is_owner' => false
                ]
            ]);
        }
        
        return response()->json([
            'message' => 'تم جلب فريقك بنجاح',
            'data' => new \App\Http\Resources\MyTeamResource($teamData)
        ])->setMaxAge(300)->setPublic();
    }
    
    public function updateName(UpdateTeamNameRequest $request): JsonResponse
    {
        $team = Team::where('owner_id', auth()->id())->firstOrFail();
        
        $team->update(['name' => $request->input('name')]);
        
        // مسح كاش المستخدم الحالي فقط
        $this->clearUserCache(auth()->id());
        
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
        
        // مسح كاش الفريق
        \App\Services\CacheService::clearTeamCache(auth()->id(), $memberId, true); // حذف عضو = حالة حرجة
        
        return response()->json([
            'message' => 'تم إزالة العضو من فريقك بنجاح'
        ]);
    }
    
    /**
     * عرض مهام الفريق
     */
    public function getTeamTasks(\App\Http\Requests\Api\TeamTasksRequest $request): JsonResponse
    {
        $userId = auth()->id();
        $team = Team::where('owner_id', $userId)->first();
        if (!$team) {
            return response()->json([
                'message' => 'لا تملك فريقاً. يرجى إنشاء فريق لعرض المهام.',
                'data' => null
            ], 404);
        }
        
        $page = $request->input('page', 1);
        $perPage = min($request->input('per_page', 10), 50);
        $status = $request->input('status');
        $withStages = $request->boolean('with_stages', true);
        $withCounts = $request->boolean('with_counts', true);
        $searchQuery = $request->input('q');
        
        $statusKey = $status ?: 'all';
        $searchKey = $searchQuery ? md5($searchQuery) : 'none';
        $cacheKey = "team_tasks_{$userId}_page_{$page}_per_{$perPage}_status_{$statusKey}_stages_{$withStages}_counts_{$withCounts}_q_{$searchKey}";
        
        $tasksData = Cache::remember($cacheKey, 300, function () use ($team, $userId, $page, $perPage, $status, $withStages, $withCounts, $searchQuery) {
            $baseQuery = Task::where('team_id', $team->id)
                ->where('creator_id', $userId)
                ->select('id', 'title', 'description', 'status', 'progress', 'receiver_id', 'creator_id', 'team_id', 'created_at', 'start_date', 'due_date', 'duration_days', 'priority', 'reward_type', 'reward_amount', 'reward_description');
            
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
        
        // التحقق من أن المستلم عضو في الفريق (إذا تم توفيره)
        $receiverId = $request->input('receiver_id');
        if ($receiverId) {
            $isMember = false;
            
            // تحقق من أن العضو هو مالك الفريق
            if ($team->owner_id == $receiverId) {
                $isMember = true;
            } else {
                // تحقق من أن العضو موجود في الفريق
                $memberExists = DB::table('team_members')
                    ->where('team_id', $team->id)
                    ->where('user_id', $receiverId)
                    ->exists();
                    
                if ($memberExists) {
                    $isMember = true;
                }
            }
            
            if (!$isMember) {
                return response()->json([
                    'message' => 'المستلم ليس عضواً في الفريق',
                    'data' => null
                ], 400);
            }
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
            // تسجيل بيانات الطلب للتشخيص
            \Illuminate\Support\Facades\Log::info('Creating task', [
                'user_id' => auth()->id(),
                'team_id' => $team->id,
                'receiver_id' => $receiverId,
                'has_selected_members' => $request->has('selected_members'),
                'selected_members' => $request->input('selected_members')
            ]);
            
            // بدء معاملة قاعدة البيانات
            \Illuminate\Support\Facades\DB::beginTransaction();
            
            // تحديد المستلمين للمهمة
            $receiverIds = [];
            
            // إضافة المستلم الرئيسي إذا تم توفيره
            if ($receiverId) {
                $receiverIds[] = $receiverId;
            }
            
            // إذا تم توفير الأعضاء المحددين
            if ($request->has('selected_members')) {
                $selectedMembers = $request->input('selected_members');
                
                // معالجة البيانات إذا كانت سلسلة نصية JSON
                if (is_string($selectedMembers)) {
                    $selectedMembers = json_decode($selectedMembers, true);
                }
                
                // معالجة البيانات إذا كانت مصفوفة
                if (is_array($selectedMembers)) {
                    foreach ($selectedMembers as $memberId) {
                        // تحقق من أن العضو موجود في الفريق ولم يتم إضافته بالفعل
                        $isMember = false;
                        
                        // تحقق من أن العضو هو مالك الفريق
                        if ($team->owner_id == $memberId) {
                            $isMember = true;
                        } else {
                            // تحقق من أن العضو موجود في الفريق
                            $memberExists = DB::table('team_members')
                                ->where('team_id', $team->id)
                                ->where('user_id', $memberId)
                                ->exists();
                                
                            if ($memberExists) {
                                $isMember = true;
                            }
                        }
                        
                        // إضافة العضو إلى قائمة المستلمين
                        if ($isMember && !in_array($memberId, $receiverIds)) {
                            $receiverIds[] = $memberId;
                        }
                    }
                }
            }
            
            // التحقق من وجود مستلم واحد على الأقل
            if (empty($receiverIds)) {
                return response()->json([
                    'message' => 'يجب تحديد مستلم واحد على الأقل للمهمة',
                    'data' => null
                ], 400);
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
                    'start_date' => $request->input('start_date', now()->toDateString()),
                    'due_date' => $request->input('due_date'),
                    'duration_days' => $request->input('duration_days', 7),
                    'total_stages' => $request->input('total_stages', 3),
                    'reward_amount' => $request->input('reward_amount'),
                    'reward_type' => $request->input('reward_type', 'cash'),
                    'reward_description' => $request->input('reward_description'),
                    'is_multiple' => count($receiverIds) > 1,
                    'selected_members' => count($receiverIds) > 1 ? json_encode($receiverIds) : null,
                    'subscription_status' => $subscriptionStatus, // تخزين حالة الاشتراك مع المهمة بناءً على التحقق المباشر
                ]);
                
                // تحديث عدد المهام في الاشتراك
                $subscriptionService->incrementTasksCreated($user);
                
                // إنشاء مراحل المهمة تلقائيًا وفق عدد المراحل والمدة الزمنية
                $totalStages = $request->input('total_stages', 3);
                $dueDate = $request->input('due_date') ? \Carbon\Carbon::parse($request->input('due_date')) : \Carbon\Carbon::now()->addDays(7);
                $startDate = $request->input('start_date') ? \Carbon\Carbon::parse($request->input('start_date')) : \Carbon\Carbon::now();
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
                
                
                // مسح كاش الفريق
                \App\Services\CacheService::clearTeamCache(auth()->id(), $rid, false);
                
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
        // جلب جميع المهام للعضو (بدون فلترة team_id) باستخدام التخزين المؤقت
        $cacheKey = "member_tasks_stats_{$member->id}_{$team->id}";
        $memberTasks = cache()->remember($cacheKey, now()->addMinutes(5), function () use ($member) {
            return Task::where('receiver_id', $member->id)->get();
        });
        
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
        
        $status = $request->input('status');
        
        $cacheKey = "team_members_task_stats_{$team->id}_all_status_{$status}";
        
        $statsData = Cache::remember($cacheKey, 300, function () use ($team, $status) {
            // جلب أعضاء الفريق مع إحصائيات المهام
            $query = $team->members()
                ->select('users.id', 'users.name', 'users.email', 'users.avatar_url')
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
            $members = $query->get();
            
            return [
                'members' => $members,
                'total' => $total
            ];
        });
        
        $mappedData = $statsData['members']->map(function ($member) use ($status) {
            return [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'avatar_url' => $member->avatar_url ? 'https://www.moezez.com/storage/avatar/' . $member->avatar_url : null,
                'total_tasks' => $member->total_tasks,
                'pending_tasks' => $member->pending_tasks,
                'in_progress_tasks' => $member->in_progress_tasks,
                'completed_tasks' => $member->completed_tasks,
                'filtered_tasks' => $status ? $member->filtered_tasks : null
            ];
        });

        return response()->json([
            'message' => 'تم جلب إحصائيات مهام أعضاء الفريق بنجاح',
            'data' => $mappedData
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

        $status = $request->input('status');
        $withStages = $request->boolean('with_stages', true);
        $searchQuery = $request->input('q');

        $cacheKey = "member_task_stats_{$team->id}_{$memberId}_status_{$status}_stages_{$withStages}_q_{$searchQuery}";

        $tasksData = Cache::remember($cacheKey, 300, function () use ($team, $memberId, $status, $withStages, $searchQuery) {
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

            $tasks = $baseQuery->orderBy('created_at', 'desc')->get();

            return [
                'tasks' => $tasks
            ];
        });

        if ($tasksData['tasks']->isEmpty()) {
            return response()->json([
                'message' => 'لا توجد مهام مرتبطة بهذا العضو حاليًا',
                'data' => TaskResource::collection(collect())
            ])->setMaxAge(300)->setPublic();
        }

        return response()->json([
            'message' => 'تم جلب مهام العضو بنجاح',
            'data' => TaskResource::collection($tasksData['tasks'])
        ])->setMaxAge(300)->setPublic();
    }
    
    /**
     * عرض بيانات الفريق مع الأعضاء (الرقم، الاسم، الصورة فقط)
     */
    public function getTeamMembers(Request $request): JsonResponse
    {
        $team = $this->getUserTeam();
        if (!$team) {
            return response()->json([
                'message' => 'لا تملك فريقاً أو لست عضوًا في أي فريق.',
                'data' => null
            ], 404);
        }

        $cacheKey = "team_members_{$team->id}_" . auth()->id();
        $teamData = Cache::remember($cacheKey, 300, function () use ($team) {
            // جلب بيانات المالك مع الحقول المطلوبة فقط وتجنب تحميل علاقة media
            $team->load([
                'owner:id,name,avatar_url',
                'members:id,name,avatar_url'
            ]);
            
            // تنسيق بيانات المالك
            $owner = new TeamMemberResource($team->owner);
            
            // تنسيق بيانات الأعضاء
            $members = TeamMemberResource::collection($team->members);
            
            return [
                'team' => [
                    'id' => $team->id,
                    'name' => $team->name,
                    'owner' => $owner,
                    'members' => $members
                ]
            ];
        });

        return response()->json([
            'message' => 'تم جلب بيانات الفريق والأعضاء بنجاح',
            'data' => $teamData['team']
        ])->setMaxAge(300)->setPublic();
    }
}
