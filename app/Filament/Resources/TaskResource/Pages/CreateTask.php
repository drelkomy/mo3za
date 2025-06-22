<?php

namespace App\Filament\Resources\TaskResource\Pages;

use App\Filament\Resources\TaskResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use App\Models\Task;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;

class CreateTask extends CreateRecord
{
    protected static string $resource = TaskResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $user = auth()->user();
        $subscription = $user->activeSubscription;

        if (!$subscription || !$subscription->package) {
            Notification::make()
                ->title('خطأ في الاشتراك')
                ->body('لا يوجد لديك اشتراك فعال أو باقة محددة.')
                ->danger()
                ->send();
            throw new Halt();
        }

        $taskLimit = $subscription->package->max_tasks ?? 0;
        // حساب المهام من تاريخ بدء الاشتراك
        $startDate = $subscription->start_date ?? $subscription->created_at;
        $currentTaskCount = Task::where('creator_id', $user->id)
            ->where('created_at', '>=', $startDate)
            ->count();
        $newTasksCount = count($data['receiver_ids']);

        if ($taskLimit > 0 && ($currentTaskCount + $newTasksCount) > $taskLimit) {
            Notification::make()
                ->title('تم الوصول لحد الباقة!')
                ->body("لا يمكنك إنشاء مهام جديدة. لقد وصلت للحد الأقصى ({$taskLimit} مهمة) منذ بداية اشتراكك.")
                ->danger()
                ->send();
            throw new Halt();
        }

        $lastTask = null;
        $receiverIds = $data['receiver_ids'];
        $taskData = $data;
        unset($taskData['receiver_ids']);

        $taskData['creator_id'] = $user->id;
        $taskData['subscription_id'] = $subscription->id;

        if (isset($taskData['duration_days'], $taskData['start_date']) && is_numeric($taskData['duration_days'])) {
            $days = intval($taskData['duration_days']);
            if ($days > 0) {
                $taskData['due_date'] = \Carbon\Carbon::parse($taskData['start_date'])->addDays($days)->toDateString();
            }
        }

        foreach ($receiverIds as $receiverId) {
            $taskData['receiver_id'] = $receiverId;
            $task = Task::create($taskData);

            if ($task->total_stages > 0) {
                $stages = [];
                for ($i = 1; $i <= $task->total_stages; $i++) {
                    $stages[] = [
                        'task_id' => $task->id,
                        'stage_number' => $i,
                        'title' => "المرحلة {$i}",
                        'status' => 'pending',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                \App\Models\TaskStage::insert($stages);
            }
            
            // تحديث عداد المهام في الاشتراك
            app(\App\Services\SubscriptionService::class)->incrementTasksCreated($user);
            
            $lastTask = $task;
        }



        Notification::make()
            ->title('تم إنشاء المهام لجميع المستلمين بنجاح')
            ->success()
            ->send();

        return $lastTask;
    }
}