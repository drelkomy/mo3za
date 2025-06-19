<?php

namespace App\Filament\Resources\TaskResource\Pages;

use App\Filament\Resources\TaskResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\Task;
use App\Models\Milestone;
use Illuminate\Database\Eloquent\Builder;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\DB;

class ParticipantTasksIndex extends ListRecords
{
    use WithFileUploads;
    
    protected static string $resource = TaskResource::class;

    public ?array $milestoneData = [];
    public $milestoneProofFiles = [];
    
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
        
        return true;
    }
    
    public function uploadMilestoneProof($milestoneId)
    {
        $this->validate([
            "milestoneProofFiles.{$milestoneId}" => 'required|file|max:10240',
        ]);
        
        $milestone = Milestone::find($milestoneId);
        
        if (!$milestone) {
            return;
        }
        
        $milestone->update([
            'proof_file' => $this->milestoneProofFiles[$milestoneId]->store('milestone-proofs', 'public'),
            'status' => 'submitted',
        ]);
        
        $this->milestoneProofFiles = [];
        
        \Filament\Notifications\Notification::make()
            ->success()
            ->title('تم رفع الإثبات بنجاح')
            ->send();
    }
    
    public function getMilestoneProofForm($milestoneId): \Filament\Forms\Form
    {
        return \Filament\Forms\Form::make($this)
            ->schema([
                \Filament\Forms\Components\FileUpload::make('proof_file')
                    ->label('ملف الإثبات')
                    ->directory('milestone-proofs')
                    ->required(),
                \Filament\Forms\Components\Textarea::make('comment')
                    ->label('تعليق')
                    ->rows(3),
            ])
            ->statePath("milestoneData.{$milestoneId}");
    }
    
    public function submitMilestoneProof($milestoneId): void
    {
        $data = $this->milestoneData[$milestoneId] ?? [];
        
        if (empty($data)) {
            return;
        }
        
        $milestone = Milestone::find($milestoneId);
        
        if (!$milestone) {
            return;
        }
        
        $milestone->update([
            'proof_file' => $data['proof_file'],
            'comment' => $data['comment'] ?? null,
            'status' => 'submitted',
        ]);
        
        \Filament\Notifications\Notification::make()
            ->success()
            ->title('تم رفع الإثبات بنجاح')
            ->send();
            
        $this->dispatch('close-modal', id: "submit-milestone-proof-{$milestoneId}");
    }
    
    protected function getTableQuery(): Builder
    {
        $user = auth()->user();
        
        // التحقق من أن الداعم لديه اشتراك نشط
        if ($user && $user->supporter_id) {
            $supporter = \App\Models\User::find($user->supporter_id);
            if (!$supporter || !$supporter->is_active || is_null($supporter->activeSubscription)) {
                // إرجاع استعلام فارغ إذا لم يكن لدى الداعم اشتراك نشط
                return Task::query()->where('id', 0);
            }
        }
        
        // استخدام استعلام مباشر للحصول على المهام
        return Task::query()
            ->where(function($q) use ($user) {
                // المهام التي المشارك هو المشارك الرئيسي فيها
                $q->where('participant_id', $user->id);
            })
            ->orWhereExists(function ($query) use ($user) {
                $query->select(\DB::raw(1))
                    ->from('task_participants')
                    ->whereColumn('task_participants.task_id', 'tasks.id')
                    ->where('task_participants.user_id', $user->id);
            });
    }
    
    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('عنوان المهمة')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('supporter.name')
                    ->label('الداعم')
                    ->sortable(),
                Tables\Columns\TextColumn::make('reward_amount')
                    ->label('المكافأة')
                    ->money('SAR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('reward_description')
                    ->label('نوع المكافأة')
                    ->limit(30)
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('حالة المهمة')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'completed' => 'success',
                        'overdue' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(function($state) {
                        if ($state === 'pending') return 'قيد التنفيذ';
                        if ($state === 'completed') return 'منجزة';
                        if ($state === 'overdue') return 'متأخرة';
                        return $state;
                    }),
                Tables\Columns\TextColumn::make('progress')
                    ->label('نسبة الإنجاز')
                    ->getStateUsing(function (Task $record) {
                        $user = auth()->user();
                        $totalMilestones = $record->milestones()->where('participant_id', $user->id)->count();
                        if ($totalMilestones === 0) {
                            return '0%';
                        }
                        $completedMilestones = $record->milestones()
                            ->where('participant_id', $user->id)
                            ->whereIn('status', ['submitted', 'approved'])
                            ->count();
                        return intval(($completedMilestones / $totalMilestones) * 100) . '%';
                    }),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('فعالة؟')
                    ->boolean()
                    ->getStateUsing(fn (Task $record): bool => $record->status !== 'completed'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('حالة المهمة')
                    ->options([
                        'pending' => 'قيد التنفيذ',
                        'completed' => 'مكتملة',
                        'overdue' => 'متأخرة',
                    ])
                    ->default('pending')
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'],
                            fn (Builder $query, $value): Builder => $query->where('status', $value)
                        );
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('upload_milestone_proof')
                    ->label('رفع إثبات للمراحل')
                    ->icon('heroicon-o-paper-clip')
                    ->modalHeading('رفع إثبات للمراحل')
                    ->modalDescription('اختر المرحلة وقم برفع الإثبات المطلوب')
                    ->form(function (Task $record) {
                        $user = auth()->user();
                        $pendingMilestones = $record->milestones()
                            ->where('status', 'pending')
                            ->where('participant_id', $user->id)
                            ->pluck('title', 'id')
                            ->toArray();
                            
                        return [
                            \Filament\Forms\Components\Select::make('milestone_id')
                                ->label('المرحلة')
                                ->options($pendingMilestones)
                                ->required()
                                ->reactive(),
                            \Filament\Forms\Components\FileUpload::make('proof_file')
                                ->label('ملف الإثبات')
                                ->directory('milestone-proofs')
                                ->required(),
                            \Filament\Forms\Components\Textarea::make('comment')
                                ->label('تعليق')
                                ->rows(3),
                        ];
                    })
                    ->action(function (array $data, Task $record): void {
                        $milestone = Milestone::find($data['milestone_id']);
                        
                        if ($milestone) {
                            $milestone->update([
                                'proof_file' => $data['proof_file'],
                                'comment' => $data['comment'] ?? null,
                                'status' => 'submitted',
                            ]);
                            
                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('تم رفع الإثبات بنجاح')
                                ->send();
                        }
                    })
                    ->visible(function (Task $record): bool {
                        $user = auth()->user();
                        return $record->milestones()
                            ->where('status', 'pending')
                            ->where('participant_id', $user->id)
                            ->count() > 0;
                    }),
                Tables\Actions\ViewAction::make()
                    ->label('عرض المهمة')
                    ->url(fn (Task $record): string => TaskResource::getUrl('view', ['record' => $record->id]))
                    ->openUrlInNewTab(),
                    
                Tables\Actions\Action::make('view_milestones')
                    ->label('عرض المراحل')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->modalHeading(fn (Task $record) => 'مراحل المهمة: ' . $record->title)
                    ->modalContent(function (Task $record) {
                        $user = auth()->user();
                        return view('filament.resources.task-resource.pages.task-milestones-modal', [
                            'record' => $record,
                            'milestones' => $record->milestones()->where('participant_id', $user->id)->get(),
                        ]);
                    })
                    ->modalWidth('md')
                    ->modalSubmitAction(false),

            ])
            ->bulkActions([])
            ->defaultSort('due_date', 'asc');
    }
    
    protected function getHeaderActions(): array
    {
        return [];
    }
}