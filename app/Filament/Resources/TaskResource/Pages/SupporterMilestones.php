<?php

namespace App\Filament\Resources\TaskResource\Pages;

use App\Filament\Resources\TaskResource;
use Filament\Resources\Pages\Page;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use App\Models\Task;
use App\Models\Milestone;

class SupporterMilestones extends Page
{
    protected static string $resource = TaskResource::class;
    
    protected static string $view = 'filament.resources.task-resource.pages.supporter-milestones';
    
    public ?Task $record = null;
    public $participantFilter = '';
    public $statusFilter = '';
    
    public function mount(Task $record): void
    {
        $this->record = $record;
    }
    
    public function getFilteredMilestonesProperty()
    {
        $milestones = $this->record->milestones();
        
        if ($this->participantFilter) {
            $milestones = $milestones->where('participant_id', $this->participantFilter);
        }
        
        if ($this->statusFilter) {
            $milestones = $milestones->where('status', $this->statusFilter);
        }
        
        return $milestones->get();
    }
    
    public function approveMilestone(Milestone $milestone): void
    {
        $milestone->update([
            'status' => 'approved',
            'completed_at' => now(),
        ]);
        
        // تحديث حالة المهمة إذا تم اكتمال جميع المراحل
        $this->record->updateTaskStatus();
        
        Notification::make()
            ->success()
            ->title('تم اعتماد المرحلة بنجاح')
            ->send();
    }
    
    public function rejectMilestone(Milestone $milestone, array $data): void
    {
        $milestone->update([
            'status' => 'rejected',
            'comment' => $data['rejection_reason'] ?? null,
        ]);
        
        Notification::make()
            ->warning()
            ->title('تم رفض المرحلة')
            ->send();
    }
    
    public function closeTask(): void
    {
        $this->record->closeTask();
        
        Notification::make()
            ->success()
            ->title('تم إغلاق المهمة بنجاح')
            ->send();
            
        $this->redirect(TaskResource::getUrl('index'));
    }
}