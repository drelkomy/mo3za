<?php

namespace App\Filament\Resources\TaskResource\Pages;

use App\Filament\Resources\TaskResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions\Action;
use Filament\Actions\SelectAction;
use App\Models\Task;
use App\Models\TaskReward;
use App\Models\Milestone;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\FileUpload;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Actions\Action as InfolistAction;
use Illuminate\Support\Facades\Storage;
use Filament\Support\Enums\ActionSize;
use Illuminate\Support\Facades\DB;

class ViewTask extends ViewRecord
{
    protected static string $view = 'filament.resources.task-resource.pages.view-task';
    
    public function getViewData(): array
    {
        $task = $this->record;
        $user = auth()->user();
        $isParticipant = $user->hasRole('مشارك');
        
        if ($isParticipant) {
            // للمشارك: نحسب فقط المراحل المخصصة له
            $milestonesQuery = $task->milestones()->where('participant_id', $user->id);
            $totalMilestones = $milestonesQuery->count();
            $completedMilestones = $milestonesQuery->where('status', 'approved')->count();
            $progress = $totalMilestones > 0 ? round(($completedMilestones / $totalMilestones) * 100) : 0;
        } else {
            // للداعم: نحسب جميع المراحل
            $totalMilestones = $task->milestones()->count();
            $completedMilestones = $task->milestones()->where('status', 'approved')->count();
            $progress = $totalMilestones > 0 ? round(($completedMilestones / $totalMilestones) * 100) : 0;
        }
        
        $dueDate = $task->due_date ? \Carbon\Carbon::parse($task->due_date)->startOfDay() : now()->startOfDay();
        $today = now()->startOfDay();
        $daysLeft = $dueDate->diffInDays($today, false);
        
        return [
            'totalMilestones' => $totalMilestones,
            'completedMilestones' => $completedMilestones,
            'progress' => $progress,
            'daysLeft' => $daysLeft,
        ];
    }
    protected function getHeaderActions(): array
    {
        return [
            SelectAction::make('status')
                ->label('تغيير الحالة')
                ->options([
                    'pending' => 'قيد الانتظار',
                    'in_progress' => 'قيد التنفيذ',
                    'completed' => 'مكتملة',
                    'cancelled' => 'ملغاة',
                ])
                ->action(function (array $data, Task $record): void {
                    $record->status = $data['status'];
                    $record->save();
                })
                ->visible(fn (Task $record): bool => auth()->user()->hasRole('داعم') && $record->status !== 'completed'),

            Action::make('close_task')
                ->label('إغلاق المهمة')
                ->color('success')
                ->form(function (Task $record) {
                    $participants = [];
                    
                    // إضافة المشارك الرئيسي
                    if ($record->participant) {
                        $participants[$record->participant_id] = $record->participant->name;
                    }
                    
                    // إضافة المشاركين الآخرين
                    foreach ($record->participants as $participant) {
                        $participants[$participant->id] = $participant->name;
                    }
                    
                    return [
                        Forms\Components\CheckboxList::make('selected_participants')
                            ->label('اختر المشاركين للمكافأة')
                            ->options($participants)
                            ->default(array_keys($participants))
                            ->columns(2)
                            ->required(),
                    ];
                })
                ->action(function (array $data, Task $record) {
                    $record->status = 'completed';
                    $record->completed_at = now();
                    $record->milestones()->update(['status' => 'approved', 'completed_at' => now()]);
                    
                    // توزيع المكافآت على المشاركين المحددين
                    if ($record->reward_amount > 0 && !empty($data['selected_participants'])) {
                        $participantCount = count($data['selected_participants']);
                        $rewardPerParticipant = $record->reward_amount / $participantCount;
                        
                        foreach ($data['selected_participants'] as $participantId) {
                            TaskReward::create([
                                'task_id' => $record->id,
                                'user_id' => $participantId,
                                'amount' => $rewardPerParticipant,
                                'type' => 'cash',
                                'status' => 'completed',
                                'awarded_at' => now(),
                            ]);
                        }
                    }
                    
                    $record->save();
                })
                ->visible(fn (Task $record): bool => auth()->user()->hasRole('داعم') && $record->status !== 'completed'),

            Action::make('close_for_all')
                ->label('إغلاق للجميع')
                ->color('success')
                ->form(function (Task $record) {
                    if (!$record->batch_uuid) return [];
                    
                    $allParticipants = [];
                    $tasksToClose = Task::where('batch_uuid', $record->batch_uuid)->get();
                    
                    foreach ($tasksToClose as $task) {
                        if ($task->status !== 'completed') {
                            // إضافة المشارك الرئيسي
                            if ($task->participant) {
                                $allParticipants[$task->participant_id] = $task->participant->name;
                            }
                            
                            // إضافة المشاركين الآخرين
                            foreach ($task->participants as $participant) {
                                $allParticipants[$participant->id] = $participant->name;
                            }
                        }
                    }
                    
                    return [
                        Forms\Components\CheckboxList::make('selected_participants')
                            ->label('اختر المشاركين للمكافأة')
                            ->options($allParticipants)
                            ->default(array_keys($allParticipants))
                            ->columns(2)
                            ->required(),
                    ];
                })
                ->action(function (array $data, Task $record) {
                    if (!$record->batch_uuid) return;
                    
                    $tasksToClose = Task::where('batch_uuid', $record->batch_uuid)->get();
                    $selectedParticipants = $data['selected_participants'] ?? [];
                    
                    foreach ($tasksToClose as $task) {
                        if ($task->status !== 'completed') {
                            $task->status = 'completed';
                            $task->milestones()->update(['status' => 'approved', 'completed_at' => now()]);
                            
                            // توزيع المكافأة فقط للمشاركين المحددين
                            if ($task->reward_amount > 0 && 
                                $task->participant_id && 
                                in_array($task->participant_id, $selectedParticipants)) {
                                
                                TaskReward::create([
                                    'task_id' => $task->id,
                                    'user_id' => $task->participant_id,
                                    'amount' => $task->reward_amount,
                                    'type' => 'cash',
                                    'status' => 'completed',
                                    'awarded_at' => now(),
                                ]);
                            }
                            
                            $task->save();
                        }
                    }
                })
                ->visible(fn (Task $record): bool => auth()->user()->hasRole('داعم') && $record->batch_uuid && $record->status !== 'completed'),
        ];
    }
    protected static string $resource = TaskResource::class;
    
    public static function getRecordFromParameters(array $parameters = []): ?Task
    {
        $id = $parameters['record'] ?? null;
        
        if (!$id) {
            return null;
        }
        
        // استخدام findOrFail بدلاً من where->first للتأكد من استرجاع السجل المحدد
        return Task::findOrFail($id);
    }
    
    public static function canAccess(array $parameters = []): bool
    {
        $user = auth()->user();
        
        if (!$user) {
            return false;
        }
        
        // مدير النظام يمكنه الوصول دائماً
        if ($user->hasRole('مدير نظام')) {
            return true;
        }
        
        // الداعم يمكنه الوصول فقط إذا كان لديه اشتراك نشط
        if ($user->hasRole('داعم')) {
            return !is_null($user->activeSubscription);
        }
        
        // المشارك يمكنه الوصول فقط إذا كان مفعل
        if ($user->hasRole('مشارك')) {
            if (!$user->is_active) {
                return false;
            }
            
            // السماح للمشارك بالوصول إلى جميع المهام المخصصة له
            return true;
        }
        
        return false;
    }
    
    public function form(Form $form): Form
    {
        $user = auth()->user();
        $isParticipant = $user->hasRole('مشارك');
        
        $schema = [
            Forms\Components\Section::make('تفاصيل المهمة')
                ->schema([
                    Forms\Components\TextInput::make('title')
                        ->label('عنوان المهمة')
                        ->disabled(),
                    Forms\Components\RichEditor::make('description')
                        ->label('الوصف')
                        ->disabled()
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('reward_amount')
                        ->label('قيمة المكافأة')
                        ->prefix('SAR')
                        ->disabled(),
                    Forms\Components\TextInput::make('status')
                        ->label('الحالة')
                        ->formatStateUsing(function($state) {
                            if ($state === 'pending') return 'قيد التنفيذ';
                            if ($state === 'completed') return 'منجزة';
                            if ($state === 'overdue') return 'متأخرة';
                            return $state;
                        })
                        ->disabled(),
                    Forms\Components\TextInput::make('supporter.name')
                        ->label('الداعم')
                        ->disabled(),
                    Forms\Components\DatePicker::make('due_date')
                        ->label('تاريخ الاستحقاق')
                        ->disabled(),
                ])->columns(2),
        ];
        
        if (!$isParticipant) {
            $schema[] = Forms\Components\Section::make('شروط الاتفاق')
                ->schema([
                    Forms\Components\RichEditor::make('terms')
                        ->label('شروط الاتفاق وآلية التنفيذ')
                        ->disabled()
                        ->columnSpanFull(),
                ]);
        }
        
        return $form->schema($schema);
    }
    
    protected function getParticipantMilestoneAction(Milestone $milestone): InfolistAction
    {
        return InfolistAction::make('submit_milestone')
            ->label('تسليم المرحلة')
            ->icon('heroicon-o-paper-clip')
            ->size(ActionSize::Large)
            ->form([
                FileUpload::make('proof_file')
                    ->label('ملف الإثبات')
                    ->required()
                    ->disk('public')
                    ->directory('milestone-proofs'),
                Forms\Components\Toggle::make('completed')
                    ->label('تم إنجاز المرحلة')
                    ->required()
                    ->default(true),
            ])
            ->action(function (array $data) use ($milestone): void {
                $milestone->update([
                    'status' => 'approved', // تغيير الحالة مباشرة إلى "تمت الموافقة" بدون الحاجة لموافقة الداعم
                    'proof_file_path' => $data['proof_file'],
                    'completed_at' => now(),
                ]);
            })
            ->visible(
                auth()->user()->hasRole('مشارك') && 
                $milestone->status === 'pending' &&
                $milestone->participant_id === auth()->id()
            );
    }
    
    protected function getApproveMilestoneAction(Milestone $milestone): InfolistAction
    {
        return InfolistAction::make('approve_milestone')
            ->label('قبول المرحلة')
            ->icon('heroicon-o-check')
            ->color('success')
            ->size(ActionSize::Large)
            ->action(function () use ($milestone): void {
                $milestone->update([
                    'status' => 'approved',
                    'completed_at' => now(),
                ]);
            })
            ->visible(
                auth()->user()->hasRole('داعم') && 
                $milestone->status === 'submitted'
            );
    }
    
    protected function getRejectMilestoneAction(Milestone $milestone): InfolistAction
    {
        return InfolistAction::make('reject_milestone')
            ->label('رفض المرحلة')
            ->icon('heroicon-o-x-mark')
            ->color('danger')
            ->size(ActionSize::Large)
            ->form([
                Forms\Components\Textarea::make('rejection_comment')
                    ->label('سبب الرفض')
                    ->required(),
            ])
            ->action(function (array $data) use ($milestone): void {
                $milestone->update([
                    'status' => 'rejected',
                    'rejection_comment' => $data['rejection_comment'],
                ]);
            })
            ->visible(
                auth()->user()->hasRole('داعم') && 
                $milestone->status === 'submitted'
            );
    }
    
    public function milestoneInfolist(Infolist $infolist): Infolist
    {
        $user = auth()->user();
        $isParticipant = $user->hasRole('مشارك');
        $today = now()->startOfDay();
        $task = $this->record;
        
        $query = Milestone::query()->where('task_id', $task->id);
        
        // إذا كان المستخدم مشارك، نعرض فقط المراحل المخصصة له
        if ($isParticipant) {
            $query->where('participant_id', $user->id);
        }
        
        // إظهار المراحل التي حان موعدها أو تم تسليمها أو الموافقة عليها أو رفضها
        // عرض جميع المراحل بغض النظر عن تاريخ الاستحقاق
        
        $milestones = $query->orderBy('due_date', 'asc')->get();
        
        $entries = [];
        
        foreach ($milestones as $index => $milestone) {
            $statusText = match ($milestone->status) {
                'pending' => 'قيد التنفيذ',
                'submitted' => 'بانتظار المراجعة',
                'approved' => 'تمت الموافقة',
                'rejected' => 'مرفوضة',
                default => $milestone->status,
            };
            
            $statusColor = match ($milestone->status) {
                'pending' => 'warning',
                'submitted' => 'info',
                'approved' => 'success',
                'rejected' => 'danger',
                default => 'gray',
            };
            
            $actions = [];
            
            if ($isParticipant && $milestone->status === 'pending' && $milestone->participant_id === $user->id) {
                $actions[] = $this->getParticipantMilestoneAction($milestone);
                
                // إضافة زر لتغيير حالة المرحلة
                $actions[] = InfolistAction::make('change_status')
                    ->label('تغيير الحالة')
                    ->icon('heroicon-o-arrow-path')
                    ->size(ActionSize::Large)
                    ->color('warning')
                    ->form([
                        Forms\Components\Select::make('status')
                            ->label('الحالة')
                            ->options([
                                'pending' => 'قيد التنفيذ',
                                'approved' => 'تم الإنجاز',
                            ])
                            ->required()
                            ->default($milestone->status),
                    ])
                    ->action(function (array $data) use ($milestone): void {
                        $milestone->update([
                            'status' => $data['status'],
                            'completed_at' => $data['status'] === 'approved' ? now() : null,
                        ]);
                    });
            }
            
            // حساب رقم المرحلة وتاريخها وفقاً للتقسيمة
            $milestoneNumber = $milestone->order ?? $index + 1;
            $totalMilestones = $task->milestones()->count();
            $milestoneTitle = "المرحلة {$milestoneNumber} من {$totalMilestones}: {$milestone->title}";
            
            $entries[] = Infolists\Components\Section::make($milestoneTitle)
                ->schema([
                    Infolists\Components\TextEntry::make('description')
                        ->label('الوصف')
                        ->html()
                        ->columnSpanFull(),
                    Infolists\Components\TextEntry::make('due_date')
                        ->label('تاريخ الاستحقاق')
                        ->date(),
                    Infolists\Components\TextEntry::make('completed_at')
                        ->label('تاريخ التنفيذ')
                        ->date()
                        ->visible(fn() => $milestone->completed_at !== null),
                    Infolists\Components\TextEntry::make('status')
                        ->label('الحالة')
                        ->state($statusText)
                        ->badge()
                        ->color($statusColor),
                    Infolists\Components\TextEntry::make('proof_file')
                        ->label('ملف الإثبات')
                        ->state($milestone->proof_file_path ? 'تم الرفع' : 'لا يوجد')
                        ->url($milestone->proof_file_path ? Storage::url($milestone->proof_file_path) : null)
                        ->openUrlInNewTab(),
                ])
                ->columns(3)
                ->extraAttributes(['class' => 'mb-6'])
                ->headerActions($actions);
                
            if ($milestone->status === 'rejected' && $milestone->rejection_comment) {
                $entries[] = Infolists\Components\TextEntry::make('rejection_comment')
                    ->label('سبب الرفض')
                    ->state($milestone->rejection_comment)
                    ->columnSpanFull();
            }
        }
        
        return $infolist
            ->schema($entries);
    }
    
}