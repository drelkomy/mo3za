<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TaskStage;
use App\Models\Reward;
use App\Services\CacheService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TaskService
{
    /**
     * إنشاء مراحل المهمة
     */
    public function createTaskStages(Task $task): void
    {
        // التحقق من صحة البيانات
        if ($task->total_stages <= 0 || $task->duration_days <= 0) {
            throw new \InvalidArgumentException('عدد المراحل ومدة المهمة يجب أن تكون أكبر من صفر');
        }

        $startDate = Carbon::parse($task->start_date);
        $endDate = Carbon::parse($task->due_date);
        $daysPerStage = $task->duration_days / $task->total_stages;
        
        // استخدام transaction لضمان تماسك البيانات
        DB::transaction(function () use ($task, $startDate, $endDate, $daysPerStage) {
            for ($i = 1; $i <= $task->total_stages; $i++) {
                $stageStartDate = $startDate->copy()->addDays(floor(($i - 1) * $daysPerStage));
                
                // ضبط المرحلة الأخيرة لتنتهي في التاريخ المحدد
                if ($i === $task->total_stages) {
                    $stageEndDate = $endDate;
                } else {
                    $stageEndDate = $startDate->copy()->addDays(ceil($i * $daysPerStage) - 1);
                }
                
                TaskStage::create([
                    'task_id' => $task->id,
                    'stage_number' => $i,
                    'title' => "المرحلة {$i}",
                    'description' => "وصف المرحلة {$i} من المهمة: {$task->title}",
                    'status' => 'pending',
                    'start_date' => $stageStartDate,
                    'due_date' => $stageEndDate,
                ]);
            }
        });

        Log::info("تم إنشاء {$task->total_stages} مراحل للمهمة: {$task->title}");
    }

    /**
     * تحديث تقدم المهمة بناءً على حالة المراحل
     */
    public function updateTaskProgress(Task $task): void
    {
        $stages = $task->stages()->get(); // استخدم lazy loading للأداء الأفضل
        $completed = $stages->where('status', 'completed')->count();
        $total = $stages->count();
        
        if ($total === 0) {
            Log::warning("المهمة {$task->id} لا تحتوي على مراحل");
            return;
        }
        
        $progress = ($completed / $total) * 100;
        
        $task->update([
            'progress' => round($progress, 2), // حفظ بدقة عشرية
            'status' => $this->determineTaskStatus($progress),
        ]);
    }

    /**
     * تحديد حالة المهمة بناءً على التقدم
     */
    private function determineTaskStatus(float $progress): string
    {
        return match (true) {
            $progress >= 100.0 => 'completed',
            $progress > 0 => 'in_progress',
            default => 'pending',
        };
    }

    /**
     * إكمال مرحلة مع إضافة ملاحظات الإثبات
     */
    public function completeStage(TaskStage $stage, ?string $proofNotes = null): void
    {
        if ($stage->status === 'completed') {
            Log::info("المرحلة {$stage->id} مكتملة مسبقاً");
            return;
        }

        DB::transaction(function () use ($stage, $proofNotes) {
            $stage->update([
                'status' => 'completed',
                'proof_notes' => $proofNotes,
                // يمكن إضافة completed_at عند إضافة العمود لقاعدة البيانات
                // 'completed_at' => now(),
            ]);

            $this->updateTaskProgress($stage->task);
            
            Log::info("تم إكمال المرحلة {$stage->stage_number} للمهمة {$stage->task_id}");
        });
    }

    /**
     * إكمال المهمة بواسطة القائد
     */
    public function completeTaskByLeader(Task $task, bool $distributeReward = true): void
    {
        if ($task->status === 'completed') {
            Log::info("المهمة {$task->id} مكتملة مسبقاً");
            return;
        }

        DB::transaction(function () use ($task, $distributeReward) {
            $task->update([
                'status' => 'completed',
                'progress' => 100,
                // 'completed_at' => now(), // سيتم تفعيله عند إضافة العمود
            ]);

            if ($distributeReward && $this->canDistributeReward($task)) {
                $this->distributeReward($task);
            }
            
            Log::info("تم إكمال المهمة {$task->id} بواسطة القائد");
        });
    }

    /**
     * التحقق من إمكانية توزيع المكافأة
     */
    private function canDistributeReward(Task $task): bool
    {
        return $task->reward_amount > 0 
            && $task->assigned_to 
            && !$task->reward_distributed;
    }

    /**
     * توزيع المكافأة على المستخدم المكلف
     */
    private function distributeReward(Task $task): void
    {
        try {
            Reward::create([
                'task_id' => $task->id,
                'receiver_id' => $task->receiver_id,
                'giver_id' => $task->creator_id,
                'amount' => $task->reward_amount,
                'status' => 'received',
                'notes' => 'مكافأة إنجاز مهمة' . ($task->reward_description ? ': ' . $task->reward_description : ''),
            ]);

            $task->update([
                'reward_distributed' => true,
                'reward_distributed_at' => now(),
            ]);

            // مسح كاش الفريق
            CacheService::clearTeamCache($task->creator_id, $task->receiver_id, false);

            Log::info("تم توزيع مكافأة بقيمة {$task->reward_amount} للمستخدم {$task->receiver_id}");
            
        } catch (\Exception $e) {
            Log::error("فشل في توزيع المكافأة للمهمة {$task->id}: " . $e->getMessage());
            throw $e;
        }
    }
    


    /**
     * الحصول على إحصائيات المهمة
     */
    public function getTaskStatistics(Task $task): array
    {
        $stages = $task->stages()->get();
        
        return [
            'total_stages' => $stages->count(),
            'completed_stages' => $stages->where('status', 'completed')->count(),
            'pending_stages' => $stages->where('status', 'pending')->count(),
            'in_progress_stages' => $stages->where('status', 'in_progress')->count(),
            'progress_percentage' => $task->progress,
            'days_remaining' => now()->diffInDays($task->due_date, false),
            'is_overdue' => now()->isAfter($task->due_date) && $task->status !== 'completed',
        ];
    }

    /**
     * إعادة تعيين مرحلة (إلغاء الإكمال)
     */
    public function resetStage(TaskStage $stage): void
    {
        if ($stage->status !== 'completed') {
            return;
        }

        DB::transaction(function () use ($stage) {
            $stage->update([
                'status' => 'pending',
                'proof_notes' => null,
                // 'completed_at' => null,
            ]);

            $this->updateTaskProgress($stage->task);
            
            Log::info("تم إعادة تعيين المرحلة {$stage->stage_number} للمهمة {$stage->task_id}");
        });
    }
}
