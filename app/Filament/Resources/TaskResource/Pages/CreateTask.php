<?php

namespace App\Filament\Resources\TaskResource\Pages;

use App\Filament\Resources\TaskResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use App\Models\Task;
use Filament\Notifications\Notification;

class CreateTask extends CreateRecord
{
    protected static string $resource = TaskResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $lastTask = null;
        $receiverIds = $data['receiver_ids'];
        $taskData = $data;
        unset($taskData['receiver_ids']); // Unset as we will loop over it

        // Set common data for all tasks
        $user = auth()->user();
        $subscription = $user?->activeSubscription();
        $taskData['creator_id'] = $user->id;
        $taskData['subscription_id'] = $subscription?->id;

        // Calculate due_date once
        if (isset($taskData['duration_days'], $taskData['start_date']) && is_numeric($taskData['duration_days'])) {
            $days = intval($taskData['duration_days']);
            if ($days > 0) {
                $taskData['due_date'] = \Carbon\Carbon::parse($taskData['start_date'])->addDays($days)->toDateString();
            }
        }

        // Loop through each receiver and create a task and its stages
        foreach ($receiverIds as $receiverId) {
            $taskData['receiver_id'] = $receiverId;

            $task = Task::create($taskData);

            // Create stages for the newly created task
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
            
            $lastTask = $task;
        }

        Notification::make()
            ->title('تم إنشاء المهام لجميع المستلمين بنجاح')
            ->success()
            ->send();

        // Return the last task to ensure Filament's redirect works as expected
        return $lastTask;
    }
}