<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TaskWithStagesResource;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class TaskController extends Controller
{
    public function myTasks(Request $request): JsonResponse
    {
        $page = $request->input('page', 1);
        $perPage = min($request->input('per_page', 10), 50);
        $status = $request->input('status');
        
        $cacheKey = "my_tasks_" . auth()->id() . "_page_{$page}_per_{$perPage}_status_{$status}";
        
        $tasksData = Cache::remember($cacheKey, 300, function () use ($page, $perPage, $status) {
            $query = Task::where('assigned_to', auth()->id())
                ->with(['assignedTo:id,name', 'creator:id,name', 'stages' => function($q) {
                    $q->orderBy('stage_number');
                }])
                ->withCount('stages');
            
            if ($status) {
                $query->where('status', $status);
            }
            
            $tasks = $query->orderBy('created_at', 'desc')
                ->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();
            
            $total = Task::where('assigned_to', auth()->id())
                ->when($status, fn($q) => $q->where('status', $status))
                ->count();
            
            return [
                'tasks' => $tasks,
                'total' => $total,
                'current_page' => $page,
                'per_page' => $perPage,
                'last_page' => ceil($total / $perPage)
            ];
        });
        
        return response()->json([
            'message' => 'تم جلب مهامي بنجاح',
            'data' => TaskWithStagesResource::collection($tasksData['tasks']),
            'meta' => [
                'total' => $tasksData['total'],
                'current_page' => $tasksData['current_page'],
                'per_page' => $tasksData['per_page'],
                'last_page' => $tasksData['last_page']
            ]
        ])->setMaxAge(300)->setPublic();
    }

    public function completeStage(\App\Http\Requests\Api\CompleteStageRequest $request): JsonResponse
    {
        $stage = \App\Models\TaskStage::findOrFail($request->stage_id);
        
        // التحقق من أن المستخدم مكلف بهذه المهمة
        if ($stage->task->assigned_to !== auth()->id()) {
            return response()->json(['message' => 'غير مصرح لك بإتمام هذه المرحلة'], 403);
        }
        
        // التحقق من أن المرحلة لم تكتمل بعد
        if ($stage->status === 'completed') {
            return response()->json(['message' => 'هذه المرحلة مكتملة بالفعل'], 400);
        }
        
        // رفع الملفات إن وجدت
        $uploadedFiles = [];
        if ($request->hasFile('proof_files')) {
            foreach ($request->file('proof_files') as $file) {
                $path = $file->store('task_proofs/' . $stage->task_id, 'public');
                $uploadedFiles[] = [
                    'name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'size' => $file->getSize(),
                ];
            }
        }
        
        // إتمام المرحلة
        $stage->update([
            'status' => 'completed',
            'completed_at' => now(),
            'proof_notes' => $request->proof_notes,
            'proof_files' => $uploadedFiles,
        ]);
        
        // تحديث تقدم المهمة
        $taskService = app(\App\Services\TaskService::class);
        $taskService->updateTaskProgress($stage->task);
        
        // مسح الـ cache
        Cache::forget('my_tasks_' . auth()->id() . '_*');
        
        return response()->json([
            'message' => 'تم إتمام المرحلة بنجاح',
            'stage' => new \App\Http\Resources\TaskStageResource($stage->fresh())
        ]);
    }

    public function closeTask(\App\Http\Requests\Api\CloseTaskRequest $request): JsonResponse
    {
        $task = \App\Models\Task::findOrFail($request->task_id);
        
        // التحقق من أن المستخدم هو منشئ المهمة
        if ($task->creator_id !== auth()->id()) {
            return response()->json(['message' => 'غير مصرح لك بإغلاق هذه المهمة'], 403);
        }
        
        // التحقق من أن المهمة لم تغلق بعد
        if (in_array($task->status, ['completed', 'not_completed'])) {
            return response()->json(['message' => 'هذه المهمة مغلقة بالفعل'], 400);
        }
        
        $taskService = app(\App\Services\TaskService::class);
        
        if ($request->status === 'completed') {
            // إغلاق بحالة مكتمل مع توزيع المكافأة
            $taskService->completeTaskByLeader($task, true);
            $message = 'تم إغلاق المهمة بنجاح وتوزيع المكافأة';
        } else {
            // إغلاق بحالة غير مكتمل بدون توزيع مكافأة
            $task->update([
                'status' => 'not_completed',
                'completed_at' => now(),
            ]);
            $message = 'تم إغلاق المهمة بدون إنجاز';
        }
        
        // مسح الـ cache
        Cache::forget('my_tasks_' . $task->assigned_to . '_*');
        Cache::forget('team_tasks_' . $task->team_id . '_*');
        
        return response()->json([
            'message' => $message,
            'task' => new \App\Http\Resources\TaskResource($task->fresh()->load('assignedTo', 'creator'))
        ]);
    }

    public function myRewards(Request $request): JsonResponse
    {
        $page = $request->input('page', 1);
        $perPage = min($request->input('per_page', 10), 50);
        $status = $request->input('status');
        
        $cacheKey = "my_rewards_" . auth()->id() . "_page_{$page}_per_{$perPage}_status_{$status}";
        
        $rewardsData = Cache::remember($cacheKey, 300, function () use ($page, $perPage, $status) {
            $query = \App\Models\Reward::where('user_id', auth()->id())
                ->with(['task:id,title', 'distributedBy:id,name'])
                ->orderBy('distributed_at', 'desc');
            
            if ($status) {
                $query->where('status', $status);
            }
            
            $rewards = $query->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();
            
            $total = \App\Models\Reward::where('user_id', auth()->id())
                ->when($status, fn($q) => $q->where('status', $status))
                ->count();
            
            $totalAmount = \App\Models\Reward::where('user_id', auth()->id())
                ->when($status, fn($q) => $q->where('status', $status))
                ->sum('amount');
            
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
            'message' => 'تم جلب مكافآتي بنجاح',
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
}