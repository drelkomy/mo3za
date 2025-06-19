<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TaskResource\Pages;
use App\Models\Task;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class TaskResource extends Resource
{
    protected static ?string $model = Task::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationLabel = 'المهام';
    protected static ?string $label = 'مهمة';
    protected static ?string $pluralLabel = 'المهام';
    protected static ?string $navigationGroup = 'المهام';
    protected static ?int $navigationSort = 10;

    protected static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasRole('admin');
    }

    public static function getEloquentQuery(): Builder
    {
        $user = Auth::user();
        $query = parent::getEloquentQuery();
        
        // العضو غير الأدمن يرى فقط المهام المسندة إليه أو عضو فيها
        if ($user->hasRole('member') && !$user->hasRole('admin')) {
            $query->where(function($q) use ($user) {
                $q->where('member_id', $user->id)
                  ->orWhereHas('members', function($q) use ($user) {
                      $q->where('user_id', $user->id);
                  });
            });
        }
        
        return $query;
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('admin');
    }
    public static function canCreate(): bool
    {
        return auth()->user()?->hasRole('admin');
    }
    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->hasRole('admin');
    }
    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->hasRole('admin');
    }
    public static function canForceDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->hasRole('admin');
    }
    public static function canRestore(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->hasRole('admin');
    }
    public static function canReplicate(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->hasRole('admin');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('بيانات المهمة')
                ->schema([
                    Forms\Components\TextInput::make('title')
                        ->label('عنوان المهمة')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\Textarea::make('description')
                        ->label('وصف المهمة')
                        ->required()
                        ->columnSpanFull(),
                    Forms\Components\DatePicker::make('deadline')
                        ->label('الموعد النهائي')
                        ->required(),
                    Forms\Components\Select::make('status')
                        ->label('الحالة')
                        ->options([
                            'pending' => 'قيد الانتظار',
                            'in_progress' => 'قيد التنفيذ',
                            'completed' => 'مكتملة',
                            'cancelled' => 'ملغية',
                        ])
                        ->default('pending')
                        ->required(),
                    Forms\Components\TextInput::make('reward_amount')
                        ->label('قيمة المكافأة')
                        ->numeric()
                        ->required()
                        ->visible(fn () => Auth::user()->hasRole('member')),
                    Forms\Components\Select::make('member_id')
                        ->label('العضو الرئيسي')
                        ->relationship('member', 'name')
                        ->searchable()
                        ->preload()
                        ->visible(fn () => Auth::user()->hasRole('member')),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('عنوان المهمة')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'gray',
                        'in_progress' => 'warning',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'قيد الانتظار',
                        'in_progress' => 'قيد التنفيذ',
                        'completed' => 'مكتملة',
                        'cancelled' => 'ملغية',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('deadline')
                    ->label('الموعد النهائي')
                    ->date(),
                Tables\Columns\TextColumn::make('reward_amount')
                    ->label('قيمة المكافأة')
                    ->money('SAR')
                    ->visible(fn () => Auth::user()->hasRole('member')),
                Tables\Columns\TextColumn::make('member.name')
                    ->label('العضو الرئيسي')
                    ->visible(fn () => Auth::user()->hasRole('member')),
                Tables\Columns\TextColumn::make('supporter.name')
                    ->label('الداعم')
                    ->visible(fn () => Auth::user()->hasRole('member')),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('الحالة')
                    ->options([
                        'pending' => 'قيد الانتظار',
                        'in_progress' => 'قيد التنفيذ',
                        'completed' => 'مكتملة',
                        'cancelled' => 'ملغية',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTasks::route('/'),
            'create' => Pages\CreateTask::route('/create'),
            'edit' => Pages\EditTask::route('/{record}/edit'),
            'view' => Pages\ViewTask::route('/{record}'),
        ];
    }
}