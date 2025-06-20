<?php

namespace App\Filament\Resources\TeamResource\Pages;

use App\Filament\Resources\TeamResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTeam extends EditRecord
{
    protected static string $resource = TeamResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
        ];
    }

    public function mount(int | string $record): void
    {
        parent::mount($record);
        
        // التأكد من أن المستخدم هو مالك الفريق
        if ($this->record->owner_id !== auth()->id() && !auth()->user()->hasRole('admin')) {
            abort(403);
        }
    }
}