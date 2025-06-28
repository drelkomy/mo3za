<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\TaskDetailsRequest;
use App\Http\Resources\TaskDetailsResource;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

class TaskDetailsController extends Controller
{
    public function __invoke(TaskDetailsRequest $request): JsonResponse
    {
        $taskId = $request->input('task_id');
        $userId = auth()->id();
        
        // تحديد عدد الطلبات
        $key = "task_details_rate_{$userId}";
        if (RateLimiter::tooManyAttempts($key, 30)) {
            return response()->json([
                'message' => 'تم تجاوز الحد الأقصى لعدد الطلبات. حاول مرة أخرى لاحقًا.',
            ], 429);
        }
        RateLimiter::hit($key, 60);
        
        // استخدام الكاش لتحسين الأداء
        $cacheKey = "task_details_{$taskId}_{$userId}";
        $task = Cache::remember($cacheKey, 300, function () use ($taskId, $userId) {
            $task = Task::with([
                'creator:id,name,email',
                'receiver:id,name,email',
                'team:id,name',
                'stages' => function($q) {
                    $q->orderBy('stage_number');
                }
            ])->findOrFail($taskId);
            
            // التحقق من الصلاحيات
            if ($task->creator_id !== $userId && $task->receiver_id !== $userId) {
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
    }
}
