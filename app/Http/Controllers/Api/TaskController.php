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
    private function clearTaskCache(int $creatorId, int $receiverId, int $taskId = null): void
    {
        $this->clearUserCache($creatorId);
        if ($receiverId !== $creatorId) {
            $this->clearUserCache($receiverId);
        }
        
        // مسح كاش تفاصيل المهمة
        if ($taskId) {
            Cache::forget("task_details_{$taskId}_{$creatorId}");
            Cache::forget("task_details_{$taskId}_{$receiverId}");
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
        $this->clearTaskCache($task->creator_id, $task->receiver_id, $task->id);
        
        return response()->json([
            'message' => 'تم تحديث حالة المهمة بنجاح',
            'data' => new TaskResource($task)
        ]);
    }
    
    /**
     * إكمال مرحلة من مراحل المهمة
     */
    public function completeStage(\App\Http\Requests\Api\StageCompletionRequest $request): JsonResponse
    {
        // تحديد عدد الطلبات
        $key = "complete_stage_" . auth()->id();
        if (RateLimiter::tooManyAttempts($key, 30)) {
            return response()->json(['message' => 'تم تجاوز الحد الأقصى للطلبات'], 429);
        }
        RateLimiter::hit($key, 60);
        
        $stage = TaskStage::with('task')->findOrFail($request->stage_id);
        $task = $stage->task;
        
        // التحقق من الصلاحيات
        if ($task->receiver_id !== auth()->id()) {
            return response()->json(['message' => 'غير مصرح لك بإكمال هذه المرحلة'], 403);
        }
        
        if ($stage->status === 'completed') {
            return response()->json(['message' => 'المرحلة مكتملة بالفعل'], 422);
        }
        
        \Log::info('Complete stage request:', [
            'stage_id' => $request->stage_id,
            'has_file' => $request->hasFile('proof_image'),
            'content_type' => $request->header('Content-Type')
        ]);
        
        DB::beginTransaction();
        try {
            
            $updateData = [
                'status' => 'completed',
                'completed_at' => now(),
                'proof_notes' => $request->proof_notes
            ];
            
            // إضافة قيمة افتراضية لـ proof_files إذا لم يكن هناك ملف
            if (!$request->hasFile('proof_image')) {
                $updateData['proof_files'] = [
                    'type' => 'text_only',
                    'notes' => $request->proof_notes ?? 'تم إكمال المرحلة',
                    'completed_at' => now()->toISOString()
                ];
            }
            
            // معالجة الصورة
            if ($request->hasFile('proof_image')) {
                $file = $request->file('proof_image');
                
                // التحقق من صحة الملف
                if (!$file->isValid()) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'الملف غير صالح',
                        'error' => $file->getErrorMessage()
                    ], 422);
                }
                
                // التحقق من نوع الملف
                $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
                if (!in_array($file->getMimeType(), $allowedTypes)) {
                    DB::rollBack();
                    return response()->json(['message' => 'نوع الملف غير مدعوم'], 422);
                }
                
                // التحقق من حجم الملف (2MB)
                if ($file->getSize() > 2097152) {
                    DB::rollBack();
                    return response()->json(['message' => 'حجم الملف يتجاوز 2 ميجابايت'], 422);
                }
                
                try {
                    $filename = 'stage_' . $request->stage_id . '_' . time() . '.' . $file->getClientOriginalExtension();
                    \Log::info('Attempting to store file:', ['filename' => $filename]);
                    $path = $file->storeAs('stages', $filename, 'public');
                    \Log::info('File stored:', ['path' => $path, 'full_path' => storage_path('app/public/' . $path)]);
                    
                    if (!$path) {
                        throw new \Exception('فشل في حفظ الملف');
                    }
                    
                    // التحقق من وجود الملف
                    if (!file_exists(storage_path('app/public/' . $path))) {
                        throw new \Exception('الملف لم يتم حفظه بشكل صحيح');
                    }
                    
                    $fileData = [
                        'path' => $path,
                        'url' => asset('storage/' . $path),
                        'name' => $file->getClientOriginalName(),
                        'size' => $file->getSize(),
                        'type' => $file->getMimeType(),
                        'uploaded_at' => now()->toISOString()
                    ];
                    // ملاحظة: يجب التأكد من أن عمود proof_files في جدول task_stages من نوع JSON لتخزين البيانات بشكل صحيح
                    $updateData['proof_files'] = json_encode($fileData);
                    \Log::info('File data prepared:', $fileData);
                    
                } catch (\Exception $fileError) {
                    DB::rollBack();
                    \Log::error('خطأ في رفع الملف: ' . $fileError->getMessage());
                    return response()->json(['message' => 'فشل في رفع الملف'], 500);
                }
            }
            
            \Log::info('Updating stage with data:', $updateData);
            $updated = $stage->update($updateData);
            \Log::info('Update result:', ['success' => $updated]);
            
            // تحديث مباشر لقاعدة البيانات إذا فشل الحفظ
            if (isset($updateData['proof_files'])) {
                DB::table('task_stages')
                    ->where('id', $stage->id)
                    ->update(['proof_files' => json_encode($updateData['proof_files'])]);
                \Log::info('Direct DB update for proof_files completed');
            }
            
            $freshStage = $stage->fresh();
            \Log::info('Fresh stage proof_files:', ['proof_files' => $freshStage->proof_files]);
            $task->updateProgress();
            
            DB::commit();
            
            // مسح الكاش مع تفاصيل المهمة
            $this->clearTaskCache($task->creator_id, $task->receiver_id, $task->id);
            
            $response = [
                'message' => 'تم إكمال المرحلة بنجاح',
                'data' => new \App\Http\Resources\StageResource($stage->fresh())
            ];
            
            // إضافة معلومات الملف إذا تم رفعه
            if ($request->hasFile('proof_image')) {
                $response['file_uploaded'] = true;
                $response['file_info'] = 'File uploaded successfully';
            }
            
            return response()->json($response);
            
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('خطأ في إكمال المرحلة: ' . $e->getMessage());
            return response()->json([
                'message' => 'حدث خطأ أثناء إكمال المرحلة',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
    
    /**
     * إغلاق المهمة
     */
    public function closeTask(\App\Http\Requests\Api\TaskClosureRequest $request): JsonResponse
    {
        $taskId = $request->input('task_id');
        $task = Task::findOrFail($taskId);
        
        // التحقق من الصلاحيات
        if ($task->creator_id !== auth()->id()) {
            return response()->json(['message' => 'غير مصرح لك بإغلاق هذه المهمة'], 403);
        }
        
        // معالجة الصورة المرفوعة
        $proofImage = null;
        if ($request->hasFile('proof_image')) {
            $file = $request->file('proof_image');
            $path = $file->store('task_images', 'public');
            $proofImage = [
                'path' => $path,
                'name' => $file->getClientOriginalName(),
                'type' => $file->getClientMimeType(),
                'size' => $file->getSize()
            ];
        }
        
        // تحضير البيانات للتحديث
        $updateData = [
            'status' => 'completed',
            'progress' => 100
        ];
        
        // إضافة الملاحظات إذا تم توفيرها
        if ($request->has('proof_notes')) {
            $updateData['proof_notes'] = $request->input('proof_notes');
        }
        
        // إضافة الصورة إذا تم رفعها
        if ($proofImage) {
            $updateData['proof_image'] = $proofImage;
        }
        
        // تحديث المهمة
        $task->update($updateData);
        
        // مسح كاش المستخدمين المتأثرين مع تفاصيل المهمة
        $this->clearTaskCache($task->creator_id, $task->receiver_id, $task->id);
        
        return response()->json([
            'message' => 'تم إغلاق المهمة بنجاح',
            'data' => new TaskResource($task)
        ]);
    }
    
    /**
     * تحديث due_date للمهمة
     */
    public function updateDueDate(\App\Http\Requests\Api\UpdateTaskDueDateRequest $request): JsonResponse
    {
        $task = Task::findOrFail($request->task_id);
        
        // التحقق من الصلاحيات - فقط منشئ المهمة
        if ($task->creator_id !== auth()->id()) {
            return response()->json(['message' => 'غير مصرح لك بتحديث تاريخ انتهاء هذه المهمة'], 403);
        }
        
        $task->update(['due_date' => $request->due_date]);
        
        // مسح الكاش
        $this->clearTaskCache($task->creator_id, $task->receiver_id);
        
        return response()->json([
            'message' => 'تم تحديث تاريخ انتهاء المهمة بنجاح',
            'data' => new TaskResource($task->fresh())
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
        
        $tasksData = Cache::remember($cacheKey, now()->addMinutes(3), function () use ($page, $perPage, $status) {
            $query = Task::where('receiver_id', auth()->id())
                ->with([
                    'creator:id,name,email,avatar_url',
                    'stages' => function($q) {
                        $q->orderBy('stage_number');
                    }
                ])
                ->select('id', 'title', 'description', 'status', 'progress', 'receiver_id', 'creator_id', 'team_id', 'created_at', 'due_date')
                ->withCount([
                    'stages as stages_count',
                    'stages as completed_stages' => function($q) {
                        $q->where('status', 'completed');
                    }
                ]);
            
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
        $rewardsData = Cache::remember($cacheKey, now()->addMinutes(3), function () use ($userId, $page, $perPage, $status) {
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
     */
    public function taskDetails(\App\Http\Requests\Api\TaskDetailsRequest $request): JsonResponse
    {
        $userId = auth()->id();
        $taskId = $request->task_id;
        
        // Rate limiting
        $key = "task_details_{$userId}";
        if (RateLimiter::tooManyAttempts($key, 60)) {
            return response()->json(['message' => 'تم تجاوز الحد الأقصى للطلبات'], 429);
        }
        RateLimiter::hit($key, 60);
        
        $cacheKey = "task_details_{$taskId}_{$userId}";
        
        try {
            $task = Cache::remember($cacheKey, now()->addMinutes(3), function () use ($taskId, $userId) {
                $task = Task::with([
                    'creator:id,name,email,avatar_url',
                    'receiver:id,name,email,avatar_url', 
                    'team:id,name',
                    'stages' => fn($q) => $q->orderBy('stage_number')
                ])->findOrFail($taskId);
                
                // التحقق من الصلاحيات
                if ($task->creator_id !== $userId && $task->receiver_id !== $userId) {
                    if (!$task->team_id || !$task->team->members()->where('user_id', $userId)->exists()) {
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
