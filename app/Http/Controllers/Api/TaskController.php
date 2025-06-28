<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TaskResource;
use App\Http\Resources\TaskDetailsResource;
use App\Models\Task;
use App\Models\Team;
use App\Models\TaskStage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use App\Models\Reward;

class TaskController extends Controller
{
    /**
     * مسح كاش المستخدم الحالي فقط
     */
    private function clearUserCache(int $userId): void
    {
        $patterns = [
            "my_tasks_{$userId}_*",
            "my_rewards_data_{$userId}_*"
        ];
        
        foreach ($patterns as $pattern) {
            $baseKey = str_replace('_*', '', $pattern);
            // مسح المفاتيح الأساسية المعروفة
            foreach (['all', 'pending', 'completed', 'in_progress'] as $status) {
                for ($page = 1; $page <= 3; $page++) {
                    foreach ([10, 20, 50] as $perPage) {
                        Cache::forget("{$baseKey}_page_{$page}_per_{$perPage}_status_{$status}");
                    }
                }
            }
        }
    }
    
    /**
     * مسح كاش المهمة للمستخدمين المتأثرين فقط
     */
    private function clearTaskCache(int $creatorId, int $receiverId): void
    {
        $this->clearUserCache($creatorId);
        if ($receiverId !== $creatorId) {
            $this->clearUserCache($receiverId);
        }
    }
    
    /**
     * تحديث حالة المهمة
     */
    public function updateTaskStatus(\App\Http\Requests\Api\UpdateTaskStatusRequest $request, Task $task): JsonResponse
    {
        // التحقق من الصلاحيات
        if ($task->creator_id !== auth()->id() && $task->receiver_id !== auth()->id()) {
            return response()->json(['message' => 'غير مصرح لك بتحديث هذه المهمة'], 403);
        }
        
        $status = $request->input('status');
        
        $task->update([
            'status' => $status,
            'progress' => $status === 'completed' ? 100 : ($status === 'in_progress' ? 50 : 0)
        ]);
        
        // مسح كاش المستخدمين المتأثرين فقط
        $this->clearTaskCache($task->creator_id, $task->receiver_id);
        
        return response()->json([
            'message' => 'تم تحديث حالة المهمة بنجاح',
            'data' => new TaskResource($task)
        ]);
    }
    
    /**
     * إكمال مرحلة من مراحل المهمة
     */
    public function completeStage(\App\Http\Requests\Api\CompleteStageRequest $request): JsonResponse
    {
        $stageId = $request->input('stage_id');
        $stage = TaskStage::findOrFail($stageId);
        $task = $stage->task;
        
        // التحقق من الصلاحيات
        if ($task->receiver_id !== auth()->id()) {
            return response()->json(['message' => 'غير مصرح لك بإكمال هذه المرحلة'], 403);
        }
        
        $stage->update([
            'status' => 'completed',
            'proof_notes' => $request->input('proof_notes', ''),
            'proof_files' => $request->input('proof_files', []),
            'end_date' => now()->toDateString(),
            'completed_at' => now()
        ]);
        
        // تحديث تقدم المهمة
        $task->updateProgress();
        
        // مسح كاش المستخدمين المتأثرين فقط
        $this->clearTaskCache($task->creator_id, $task->receiver_id);
        
        return response()->json([
            'message' => 'تم إكمال المرحلة بنجاح',
            'data' => new TaskResource($task->fresh(['stages']))
        ]);
    }
    
    /**
     * إغلاق المهمة
     */
    public function closeTask(\App\Http\Requests\Api\CloseTaskRequest $request): JsonResponse
    {
        $taskId = $request->input('task_id');
        $task = Task::findOrFail($taskId);
        
        // التحقق من الصلاحيات
        if ($task->creator_id !== auth()->id()) {
            return response()->json(['message' => 'غير مصرح لك بإغلاق هذه المهمة'], 403);
        }
        
        $task->update([
            'status' => 'completed',
            'progress' => 100,
            'proof_notes' => $request->input('proof_notes', ''),
            'proof_files' => $request->input('proof_files', [])
        ]);
        
        // مسح كاش المستخدمين المتأثرين فقط
        $this->clearTaskCache($task->creator_id, $task->receiver_id);
        
        return response()->json([
            'message' => 'تم إغلاق المهمة بنجاح',
            'data' => new TaskResource($task)
        ]);
    }
    
    /**
     * عرض مهامي
     */
    public function myTasks(\App\Http\Requests\Api\MyTasksRequest $request): JsonResponse
    {
        $page = $request->input('page', 1);
        $perPage = min($request->input('per_page', 10), 50);
        $status = $request->input('status');
        
        $statusKey = $status ?: 'all';
        $cacheKey = "my_tasks_" . auth()->id() . "_page_{$page}_per_{$perPage}_status_{$statusKey}";
        
        $tasksData = Cache::remember($cacheKey, 300, function () use ($page, $perPage, $status) {
            $query = Task::where('receiver_id', auth()->id())
                ->with([
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
            
            $total = Task::where('receiver_id', auth()->id())
                ->when($status, fn($q) => $q->where('status', $status))
                ->count();
                
            $statusCounts = Task::where('receiver_id', auth()->id())
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
                'message' => 'لا توجد مهام مسندة إليك حاليًا',
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
            'message' => 'تم جلب مهامك بنجاح',
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
     * إضافة دالة للحصول على مكافآتي
     */
    public function myRewards(Request $request): JsonResponse
    {
        $userId = auth()->id();
        $key = "my_rewards_{$userId}";
        $maxAttempts = 60;
        $decaySeconds = 60;

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return response()->json([
                'message' => 'تم تجاوز الحد الأقصى لعدد الطلبات. حاول مرة أخرى لاحقًا.',
            ], 429);
        }

        RateLimiter::hit($key, $decaySeconds);

        $page = $request->input('page', 1);
        $perPage = min($request->input('per_page', 5), 50);
        $status = $request->input('status');
        
        $statusKey = $status ?: 'all';
        $cacheKey = "my_rewards_data_{$userId}_page_{$page}_per_{$perPage}_status_{$statusKey}";
        $rewardsData = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($userId, $page, $perPage, $status) {
            $query = Reward::where('receiver_id', $userId)
                ->with(['task:id,title']);
            
            if ($status) {
                $query->where('status', $status);
            }
            
            $total = $query->count();
            $totalAmount = $query->sum('amount');
            $statusCounts = DB::table('rewards')
                ->select([
                    DB::raw('SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending'),
                    DB::raw('SUM(CASE WHEN status = "received" THEN 1 ELSE 0 END) as received')
                ])
                ->where('receiver_id', $userId)
                ->when($status, fn($q) => $q->where('status', $status))
                ->first();
            
            $currentPage = $page;
            $lastPage = ceil($total / $perPage);
            
            $rewards = $query->offset(($currentPage - 1) * $perPage)
                ->limit($perPage)
                ->orderBy('created_at', 'desc')
                ->get();
            
            return [
                'rewards' => $rewards,
                'total' => $total,
                'total_amount' => $totalAmount,
                'status_counts' => [
                    'pending' => $statusCounts->pending,
                    'received' => $statusCounts->received
                ],
                'current_page' => $currentPage,
                'per_page' => $perPage,
                'last_page' => $lastPage
            ];
        });
        
        if ($rewardsData['total'] == 0) {
            return response()->json([
                'message' => 'لا توجد مكافآت مرتبطة بك حاليًا',
                'data' => [],
                'meta' => [
                    'total' => 0,
                    'total_amount' => 0,
                    'status_counts' => [],
                    'current_page' => $rewardsData['current_page'],
                    'per_page' => $rewardsData['per_page'],
                    'last_page' => $rewardsData['last_page']
                ]
            ]);
        }

        return response()->json([
            'message' => 'تم جلب مكافآتك بنجاح',
            'data' => \App\Http\Resources\RewardResource::collection($rewardsData['rewards']),
            'meta' => [
                'total' => $rewardsData['total'],
                'total_amount' => $rewardsData['total_amount'],
                'status_counts' => $rewardsData['status_counts'],
                'current_page' => $rewardsData['current_page'],
                'per_page' => $rewardsData['per_page'],
                'last_page' => $rewardsData['last_page']
            ]
        ]);
    }
    
    /**
     * عرض تفاصيل المهمة
     * 
     * تعرض رقم المهمة وتفاصيلها ومراحلها
     */
    public function taskDetails(Request $request): JsonResponse
    {
        // التحقق من وجود معرف المهمة
        $taskId = $request->query('task_id');
        if (!$taskId) {
            return response()->json(['message' => 'رقم المهمة مطلوب'], 422);
        }
        
        $userId = auth()->id();
        
        // تحديد مفتاح الكاش
        $cacheKey = "task_details_{$taskId}_{$userId}";
        
        // تحديد عدد الطلبات
        $key = "task_details_rate_{$userId}";
        $maxAttempts = 60;
        $decaySeconds = 60;
        
        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return response()->json([
                'message' => 'تم تجاوز الحد الأقصى لعدد الطلبات. حاول مرة أخرى لاحقًا.',
            ], 429);
        }
        
        RateLimiter::hit($key, $decaySeconds);
        
        // استخدام الكاش لتحسين الأداء
        try {
            $task = Cache::remember($cacheKey, 300, function () use ($taskId, $userId) {
                $task = Task::with([
                    'creator:id,name,email',
                    'receiver:id,name,email',
                    'team:id,name',
                    'stages' => function($q) {
                        $q->orderBy('stage_number');
                    }
                ])->findOrFail($taskId);
                
                // التحقق من الصلاحيات - يجب أن يكون المستخدم منشئ المهمة أو مستلمها أو عضو في الفريق
                if ($task->creator_id !== $userId && $task->receiver_id !== $userId) {
                    // تحقق إذا كان المستخدم عضو في الفريق
                    $isTeamMember = $task->team && $task->team->members()->where('user_id', $userId)->exists();
                    
                    if (!$isTeamMember) {
                        abort(403, 'غير مصرح لك بعرض هذه المهمة');
                    }
                }
                
                return $task;
            });
            
            return response()->json([
                'message' => 'تم جلب تفاصيل المهمة بنجاح',
                'data' => new TaskDetailsResource($task)
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'المهمة غير موجودة'], 404);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getStatusCode());
        } catch (\Exception $e) {
            return response()->json(['message' => 'حدث خطأ أثناء جلب تفاصيل المهمة'], 500);
        }
    }
}