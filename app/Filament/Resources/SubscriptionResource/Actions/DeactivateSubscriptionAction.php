<?php

namespace App\Filament\Resources\SubscriptionResource\Actions;

use Filament\Tables\Actions\Action;
use Filament\Notifications\Notification;

class DeactivateSubscriptionAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'إلغاء تفعيل الاشتراك';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->color('danger');

        $this->icon('heroicon-o-x-circle');

        $this->requiresConfirmation();

        $this->action(function (): void {
            // إلغاء تفعيل الاشتراك
            $this->record->update(['status' => 'expired']);
            
            // إلغاء تفعيل المشاركين التابعين للداعم
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
        });

        $this->visible(fn ($record) => $record->status === 'active');
    }
}