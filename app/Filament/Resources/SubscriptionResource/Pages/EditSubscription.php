<?php

namespace App\Filament\Resources\SubscriptionResource\Pages;

use App\Filament\Resources\SubscriptionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditSubscription extends EditRecord
{
    protected static string $resource = SubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('تفعيل الاشتراك')
                ->action(function () {
                    $this->record->update(['status' => 'active']);
                    
                    // تحديث حالة المشاركين التابعين للداعم
                    $supporter = $this->record->user;
                    if ($supporter && $supporter->hasRole('داعم')) {
                        $supporter->participants()
                            ->whereHas('roles', function ($query) {
                                $query->where('name', 'مشارك');
                            })
                            ->update(['is_active' => true]);
                    }
                    
                    Notification::make()
                        ->success()
                        ->title('تم تفعيل الاشتراك بنجاح')
                        ->body('تم تفعيل جميع المشاركين التابعين للداعم')
                        ->send();
                })
                ->visible(fn () => $this->record->status !== 'active')
                ->color('success'),
                
            Actions\Action::make('إلغاء تفعيل الاشتراك')
                ->action(function () {
                    $this->record->update(['status' => 'expired']);
                    
                    // تحديث حالة المشاركين التابعين للداعم
                    $supporter = $this->record->user;
                    if ($supporter && $supporter->hasRole('داعم')) {
                        $supporter->participants()
                            ->whereHas('roles', function ($query) {
                                $query->where('name', 'مشارك');
                            })
                            ->update(['is_active' => false]);
                    }
                    
                    Notification::make()
                        ->warning()
                        ->title('تم إلغاء تفعيل الاشتراك')
                        ->body('تم إلغاء تفعيل جميع المشاركين التابعين للداعم')
                        ->send();
                })
                ->visible(fn () => $this->record->status === 'active')
                ->color('danger')
                ->requiresConfirmation(),
                
            Actions\DeleteAction::make(),
        ];
    }
}