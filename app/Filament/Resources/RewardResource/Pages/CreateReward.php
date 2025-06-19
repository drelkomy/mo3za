<?php

namespace App\Filament\Resources\RewardResource\Pages;

use App\Filament\Resources\RewardResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateReward extends CreateRecord
{
    protected static string $resource = RewardResource::class;
    
    protected function afterCreate(): void
    {
        // تحديث حالة المهمة إلى مكتملة عند إنشاء مكافأة
        $reward = $this->record;
        $task = $reward->task;
        
        if ($task && $task->status !== 'completed') {
            $task->status = 'completed';
            $task->completed_at = now();
            
            // تحديث جميع المراحل إلى مكتملة
            $task->milestones()->update([
                'status' => 'approved',
                'completed_at' => now(),
            ]);
            
            $task->save();
        }
        
        // تحديث حالة المكافأة إلى مكتملة
        $reward->status = 'paid';
        $reward->save();
    }
}