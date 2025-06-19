<?php

namespace App\Filament\Resources\TaskResource\Pages;

use App\Filament\Resources\TaskResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;

use App\Models\Task;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CreateTask extends CreateRecord
{
    protected static string $resource = TaskResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $supporter = \App\Models\User::find($data['supporter_id']);
        if (!$supporter || !$supporter->activeSubscription) {
            throw new \Exception('الداعم المحدد غير صالح أو ليس لديه اشتراك نشط.');
        }

        $taskData = collect($data);

        $record = DB::transaction(function () use ($taskData, $data) {
            $task = static::getModel()::create($taskData->except(['participants', 'auto_create_milestones', 'milestones_count'])->all());

            $participantIds = $taskData->get('participants', []);
            if (!empty($participantIds)) {
                $task->participants()->sync($participantIds);
            }

            // If auto-creation is enabled, create a unique set of milestones for each participant.
            if ($taskData->get('auto_create_milestones', false) && !empty($participantIds)) {
                $this->createMilestonesForTask($task, $taskData->get('milestones_count'), $participantIds);
            }

            return $task;
        });

        return $record;
    }

    protected function createMilestonesForTask(Task $task, int $milestonesCount, array $participantIds)
    {
        $startDate = new \DateTime($task->start_date);
        $endDate = new \DateTime($task->due_date);
        $duration = $endDate->getTimestamp() - $startDate->getTimestamp();
        $interval = $milestonesCount > 0 ? $duration / $milestonesCount : 0;

        foreach ($participantIds as $participantId) {
            for ($i = 1; $i <= $milestonesCount; $i++) {
                $milestoneDueDate = (clone $startDate)->setTimestamp($startDate->getTimestamp() + ($interval * $i));
                $task->milestones()->create([
                    'title' => "المرحلة {$i}",
                    'description' => "وصف تلقائي للمرحلة {$i}",
                    'due_date' => $milestoneDueDate->format('Y-m-d'),
                    'status' => 'pending',
                    'participant_id' => $participantId, // Link milestone to the participant
                ]);
            }
        }
    }
}