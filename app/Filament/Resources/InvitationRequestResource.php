<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InvitationRequestResource\Pages;
use App\Models\JoinRequest;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class InvitationRequestResource extends Resource
{
    protected static ?string $model = JoinRequest::class;
    protected static ?string $navigationIcon = 'heroicon-o-envelope';
    protected static ?string $navigationLabel = 'دعوات الانضمام';

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('team.name')->label('الفريق'),
                Tables\Columns\TextColumn::make('status')->label('الحالة'),
                Tables\Columns\TextColumn::make('created_at')->label('التاريخ')->dateTime(),
            ])
            ->actions([
                Tables\Actions\Action::make('accept')
                    ->label('موافقة')
                    ->color('success')
                    ->action(function (JoinRequest $record) {
                        $record->team->members()->attach($record->user_id);
                        $record->update(['status' => 'accepted']);
                    }),
                Tables\Actions\Action::make('reject')
                    ->label('رفض')
                    ->color('danger')
                    ->action(function (JoinRequest $record) {
                        $record->update(['status' => 'rejected']);
                    }),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('user_id', Auth::id());
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvitationRequests::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
