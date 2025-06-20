<?php

namespace App\Filament\Resources\TaskResource\Pages;

use App\Filament\Resources\TaskResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateTask extends CreateRecord
{
    protected static string $resource = TaskResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();
        $subscription = $user?->activeSubscription();
        
        // لا حدود على إنشاء المهام - العضو يظل نشط حتى مع انتهاء الاشتراك
        
        $data['creator_id'] = auth()->id();
        $data['subscription_id'] = $subscription?->id;
        
        // حساب تاريخ الانتهاء بناءً على تاريخ البداية والمدة
        if (isset($data['duration_days']) && isset($data['start_date']) && is_numeric($data['duration_days'])) {
            $days = intval($data['duration_days']);
            if ($days > 0) {
                $data['due_date'] = \Carbon\Carbon::parse($data['start_date'])->addDays($days)->toDateString();
            }
        }
        
        return $data;
    }
    
    protected function afterCreate(): void
    {
        // لا حاجة لتحديث عداد المهام - العضو يظل نشط
    }
}