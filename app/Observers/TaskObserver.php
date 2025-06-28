<?php

namespace App\Observers;

use App\Models\Task;

class TaskObserver
{
    public function creating(Task $task): void
    {
        $task->calculateDueDate();
    }

    public function created(Task $task): void
    {
        $task->updateStagesDueDate();
    }

    public function updating(Task $task): void
    {
        // إعادة حساب due_date إذا تم تغيير start_date أو duration_days
        if ($task->isDirty(['start_date', 'duration_days'])) {
            $task->calculateDueDate();
        }
    }

    public function updated(Task $task): void
    {
        // تحديث due_date للمراحل إذا تم تغيير due_date
        if ($task->wasChanged('due_date')) {
            $task->updateStagesDueDate();
        }
    }
}