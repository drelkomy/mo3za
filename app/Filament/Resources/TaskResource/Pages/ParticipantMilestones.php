<?php

namespace App\Filament\Resources\TaskResource\Pages;

use App\Filament\Resources\TaskResource;
use Filament\Resources\Pages\Page;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use App\Models\Task;
use App\Models\Milestone;

class ParticipantMilestones extends Page
{
    protected static string $resource = TaskResource::class;
    
    protected static string $view = 'filament.resources.task-resource.pages.participant-milestones';
    
    public ?Task $record = null;
    
    public static function canAccess(array $parameters = []): bool
    {
        $user = auth()->user();
        
        if (!$user || !$user->hasRole('مشارك')) {
            return false;
        }
        
        // التحقق من أن المشارك مفعل
        if (!$user->is_active) {
            return false;
        }
        
        // التحقق من أن الداعم الخاص بالمشارك لديه اشتراك نشط
        if ($user->supporter_id) {
            $supporter = \App\Models\User::find($user->supporter_id);
            if (!$supporter || !$supporter->is_active || is_null($supporter->activeSubscription)) {
                return false;
            }
        }
        
        // التحقق من أن المهمة مسندة إلى هذا المشارك
        $record = self::getRecordFromParameters($parameters);
        if ($record && $record->participant_id !== $user->id) {
            return false;
        }
        
        return true;
    }
    
    public function mount(Task $record): void
    {
        $this->record = $record;
        
        // التحقق من أن المهمة مسندة إلى المشارك الحالي
        if ($record->participant_id !== auth()->id()) {
            abort(403);
        }
        
        // التحقق من أن الداعم الخاص بالمشارك لديه اشتراك نشط
        $user = auth()->user();
        if ($user->supporter_id) {
            $supporter = \App\Models\User::find($user->supporter_id);
            if (!$supporter || !$supporter->is_active || is_null($supporter->activeSubscription)) {
                abort(403, 'لا يمكنك الوصول إلى هذه المهمة لأن الداعم الخاص بك غير مفعل أو ليس لديه اشتراك نشط');
            }
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