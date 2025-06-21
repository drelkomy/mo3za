<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MyTaskResource\Pages;
use App\Models\Task;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MyTaskResource extends Resource
{
    protected static ?string $model = Task::class;
    protected static ?string $navigationIcon = 'heroicon-o-user';
    protected static ?string $navigationLabel = 'مهامي';
    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return auth()->check();
    }
    
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canView(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->check();
    }
    
    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && !auth()->user()?->hasRole('admin');
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('receiver_id', auth()->id())->where('status', 'pending')->count();
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        
        if (auth()->user()?->hasRole('admin')) {
            return $query; // الأدمن يرى جميع المهام
        }
        
        return $query->where('receiver_id', auth()->id());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->label('العنوان')->searchable(),
                Tables\Columns\TextColumn::make('creator.name')->label('منشئ المهمة'),
                Tables\Columns\TextColumn::make('progress')
                    ->label('التقدم')
                    ->formatStateUsing(fn ($state) => $state . '%')
                    ->badge()
                    ->color(fn ($state) => $state == 100 ? 'success' : ($state > 0 ? 'warning' : 'gray')),
                Tables\Columns\TextColumn::make('status')->label('الحالة')->badge(),
                Tables\Columns\TextColumn::make('due_date')->label('تاريخ الاستحقاق')->date(),
                Tables\Columns\TextColumn::make('reward_amount')->label('المكافأة')->money('SAR'),
            ])
            ->actions([
                Tables\Actions\Action::make('stages')
                    ->label('المراحل')
                    ->icon('heroicon-o-list-bullet')
                    ->url(fn (Task $record): string => route('filament.admin.resources.my-tasks.stages', $record)),
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMyTasks::route('/'),
            'stages' => Pages\TaskStages::route('/{record}/stages'),
        ];
    }
}