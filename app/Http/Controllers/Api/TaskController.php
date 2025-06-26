<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Models\Team;
use App\Models\TaskStage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TaskController extends Controller
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
        
        // مسح كاش إحصائيات الفريق لجميع الحالات الممكنة
        $statuses = ['', 'pending', 'in_progress', 'completed'];
        $perPages = [10, 20, 30, 40, 50];
        for ($i = 1; $i <= 3; $i++) {
            foreach ($statuses as $status) {
                foreach ($perPages as $perPage) {
                    Cache::forget("team_members_task_stats_{$teamId}_page_{$i}_per_{$perPage}_status_{$status}");
                }
            }
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
        
        // مسح الكاش المتعلق بالمهمة
        $this->clearTaskCache($task->id, $task->team_id, $task->receiver_id);
        
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
        
        $stage->update(['status' => 'completed']);
        
        // تحديث تقدم المهمة
        $task->updateProgress();
        
        // مسح الكاش المتعلق بالمهمة
        $this->clearTaskCache($task->id, $task->team_id, $task->receiver_id);
        
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
            'completed_at' => now()
        ]);
        
        // مسح الكاش المتعلق بالمهمة
        $this->clearTaskCache($task->id, $task->team_id, $task->receiver_id);
        
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
        
        $cacheKey = "my_tasks_" . auth()->id() . "_page_{$page}_per_{$perPage}_status_{$status}";
        
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
}
