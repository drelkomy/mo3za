<?php

namespace App\Filament\Resources\SubscriptionResource\Actions;

use Filament\Tables\Actions\Action;
use Filament\Notifications\Notification;

class ActivateSubscriptionAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'تفعيل الاشتراك';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->color('success');

        $this->icon('heroicon-o-check-circle');

        $this->requiresConfirmation();

        $this->action(function (): void {
            // تفعيل الاشتراك
            $this->record->update(['status' => 'active']);
            
            // تفعيل المشاركين التابعين للداعم
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
                ->title('تم تفعيل الاشتراك')
                ->body('تم تفعيل جميع المشاركين التابعين للداعم')
                ->send();
        });

        $this->visible(fn ($record) => $record->status !== 'active');
    }
}