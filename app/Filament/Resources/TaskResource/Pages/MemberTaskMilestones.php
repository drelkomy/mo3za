<?php

namespace App\Filament\Resources\TaskResource\Pages;

use App\Filament\Resources\TaskResource;
use Filament\Resources\Pages\Page;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use App\Models\Task;
use App\Models\Milestone;

class MemberTaskMilestones extends Page
{
    protected static string $resource = TaskResource::class;
    
        protected static string $view = 'filament.resources.task-resource.pages.member-task-milestones';
    
    public ?Task $record = null;
    
    public static function canAccess(array $parameters = []): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }

        /** @var Task $task */
        $task = self::getRecordFromParameters($parameters);
        if (!$task || !$task->team) {
            return false;
        }

        // Check if the user is a member of the task's team.
        if (!$user->isMemberOf($task->team)) {
            return false;
        }

        // Check if the task is assigned to this user.
        if (!$task->members()->where('user_id', $user->id)->exists()) {
            return false;
        }

        // Check if the team owner has an active subscription.
        return $task->team->owner->hasActiveSubscription();
    }
    
    public function mount(Task $record): void
    {
        $this->record = $record;

        if (!static::canAccess(['record' => $record])) {
            abort(403, 'ليس لديك الصلاحية للوصول إلى هذه الصفحة.');
        }
    }
    
    public function submitProof(Milestone $milestone, array $data): void
    {
        $milestone->update([
            'proof_file' => $data['proof_file'],
            'comment' => $data['comment'] ?? null,
            'status' => 'submitted',
        ]);
        
        Notification::make()
            ->success()
            ->title('تم رفع الإثبات بنجاح')
            ->send();
    }
}