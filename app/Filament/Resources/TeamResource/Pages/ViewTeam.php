<?php

namespace App\Filament\Resources\TeamResource\Pages;

use App\Filament\Resources\TeamResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;

class ViewTeam extends ViewRecord
{
    protected static string $resource = TeamResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn() => $this->record->owner_id === auth()->id()),
            Actions\Action::make('inviteMember')
                ->label('دعوة عضو')
                ->icon('heroicon-o-user-plus')
                ->color('success')
                ->form([
                    \Filament\Forms\Components\TextInput::make('email')
                        ->label('البريد الإلكتروني للعضو')
                        ->email()
                        ->required()
                ])
                ->action(function (array $data) {
                    $team = $this->record;
                    $userToInvite = \App\Models\User::where('email', $data['email'])->first();
                    
                    if (!$userToInvite) {
                        Notification::make()
                            ->title('المستخدم غير موجود')
                            ->body('لا يوجد مستخدم مسجل بهذا البريد الإلكتروني')
                            ->danger()
                            ->send();
                        return;
                    }
                    
                    if ($team->hasMember($userToInvite->id) || $team->owner_id === $userToInvite->id) {
                        Notification::make()
                            ->title('عضو بالفعل')
                            ->body('هذا المستخدم عضو بالفعل في الفريق')
                            ->warning()
                            ->send();
                        return;
                    }
                    
                    $existingRequest = \App\Models\JoinRequest::where('user_id', $userToInvite->id)
                        ->where('team_id', $team->id)
                        ->first();
                    
                    if ($existingRequest) {
                        // إذا كان الطلب مرفوضاً سابقاً، يمكن إعادة إرساله
                        if ($existingRequest->status === 'rejected') {
                            $existingRequest->update(['status' => 'pending']);
                            
                            // تنظيف cache
                            \Illuminate\Support\Facades\Cache::forget("pending_requests_badge_{$userToInvite->id}");
                            
                            Notification::make()
                                ->title('تم إرسال الطلب بنجاح')
                                ->body("تم إرسال طلب انضمام إلى {$userToInvite->name}")
                                ->success()
                                ->send();
                            return;
                        }
                        
                        $status = match($existingRequest->status) {
                            'pending' => 'معلق',
                            'accepted' => 'مقبول',
                            default => $existingRequest->status
                        };
                        
                        Notification::make()
                            ->title('طلب موجود')
                            ->body("يوجد طلب انضمام {$status} بالفعل لهذا المستخدم")
                            ->warning()
                            ->send();
                        return;
                    }
                    
                    // إنشاء طلب انضمام
                    \App\Models\JoinRequest::create([
                        'user_id' => $userToInvite->id,
                        'team_id' => $team->id,
                        'status' => 'pending',
                    ]);
                    
                    // تنظيف cache
                    \Illuminate\Support\Facades\Cache::forget("pending_requests_badge_{$userToInvite->id}");
                    
                    Notification::make()
                        ->title('تم إرسال الطلب بنجاح')
                        ->body("تم إرسال طلب انضمام إلى {$userToInvite->name}")
                        ->success()
                        ->send();
                })
                ->visible(function() {
                    if ($this->record->owner_id !== auth()->id()) return false;
                    
                    $user = auth()->user();
                    $subscription = $user->activeSubscription;
                    if (!$subscription || $subscription->status !== 'active') {
                        return false;
                    }
                    
                    // تحقق من عدم استنفاد الباقة
                    $taskLimit = $subscription->package->max_tasks ?? 0;
                    if ($taskLimit > 0 && $subscription->start_date) {
                        $currentTaskCount = \App\Models\Task::where('creator_id', $user->id)
                            ->where('created_at', '>=', \Carbon\Carbon::parse($subscription->start_date))
                            ->count();
                        if ($currentTaskCount >= $taskLimit) {
                            return false;
                        }
                    }
                    
                    return true;
                }),
            Actions\Action::make('removeMember')
                ->label('إزالة عضو')
                ->icon('heroicon-o-user-minus')
                ->color('danger')
                ->form([
                    \Filament\Forms\Components\Select::make('member_id')
                        ->label('اختر العضو لإزالته')
                        ->options(fn() => $this->record->members->pluck('name', 'id')->toArray())
                        ->required()
                        ->searchable()
                ])
                ->action(function (array $data) {
                    $this->record->removeMember($data['member_id']);
                    Notification::make()
                        ->title('تم إزالة العضو بنجاح')
                        ->success()
                        ->send();
                })
                ->requiresConfirmation()
                ->visible(fn() => $this->record->owner_id === auth()->id() && $this->record->members->count() > 0),
            Actions\Action::make('leaveTeam')
                ->label('مغادرة الفريق')
                ->icon('heroicon-o-arrow-right-on-rectangle')
                ->color('gray')
                ->requiresConfirmation()
                ->action(function () {
                    $team = $this->record;
                    $userId = auth()->id();
                    
                    if ($team->owner_id === $userId) {
                        Notification::make()
                            ->title('لا يمكن لمالك الفريق مغادرته')
                            ->warning()
                            ->send();
                        return;
                    }
                    
                    $team->removeMember($userId);
                    Notification::make()
                        ->title('تمت مغادرة الفريق بنجاح')
                        ->success()
                        ->send();
                        
                    redirect()->route('filament.admin.pages.dashboard');
                })
                ->visible(fn() => $this->record->members->pluck('id')->contains(auth()->id())),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('معلومات الفريق')
                    ->schema([
                        Infolists\Components\TextEntry::make('name')
                            ->label('اسم الفريق')
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large),
                        Infolists\Components\TextEntry::make('owner.name')
                            ->label('مالك الفريق'),
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('تاريخ الإنشاء')
                            ->dateTime(),
                    ]),
                
                Infolists\Components\Section::make('أعضاء الفريق')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('members')
                            ->label('')
                            ->schema([
                                Infolists\Components\TextEntry::make('name')
                                    ->label('الاسم'),
                                Infolists\Components\TextEntry::make('email')
                                    ->label('البريد الإلكتروني'),
                                Infolists\Components\TextEntry::make('pivot.created_at')
                                    ->label('تاريخ الانضمام')
                                    ->dateTime(),
                            ])
                            ->columns(3),
                    ])
                    ->collapsible()
                    ->description($this->record->owner_id === auth()->id() ? 'استخدم زر "إدارة الأعضاء" لإزالة الأعضاء' : null),
            ]);
    }
}