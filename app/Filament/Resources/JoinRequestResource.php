<?php

namespace App\Filament\Resources;

use App\Filament\Resources\JoinRequestResource\Pages;
use App\Models\JoinRequest;
use App\Models\Team;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class JoinRequestResource extends Resource
{
    protected static ?string $model = JoinRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-plus';

    protected static ?string $navigationGroup = null;

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'طلبات الانضمام';

    protected static ?string $modelLabel = 'طلب انضمام';

    protected static ?string $pluralModelLabel = 'طلبات الانضمام';

    public static function getNavigationBadge(): ?string
    {
        return static::getEloquentQuery()->where('status', 'pending')->count();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]); // No form needed, actions are used
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('team.name')
                    ->label('الفريق')
                    ->searchable()
                    ->sortable()
                    ->default('غير محدد'),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('المستخدم')
                    ->searchable()
                    ->sortable()
                    ->default('غير محدد'),
                Tables\Columns\TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'في الانتظار',
                        'accepted' => 'مقبول',
                        'rejected' => 'مرفوض',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'accepted' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('التاريخ')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                Action::make('accept')
                    ->label('موافقة')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (JoinRequest $record) {
                        $team = $record->team;
                        $user = $record->user;

                        $team->members()->attach($user->id);
                        $record->update(['status' => 'accepted']);

                        Notification::make()
                            ->title('تم قبول الطلب')
                            ->body("تم قبول {$user->name} في فريق {$team->name}")
                            ->success()
                            ->send();
                    })
                    ->visible(fn (JoinRequest $record): bool => 
                        $record->status === 'pending' && 
                        $record->team->owner_id === Auth::id()
                    ),

                Action::make('reject')
                    ->label('رفض')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (JoinRequest $record) {
                        $record->update(['status' => 'rejected']);
                        
                        Notification::make()
                            ->title('تم رفض الطلب')
                            ->warning()
                            ->send();
                    })
                    ->visible(fn (JoinRequest $record): bool => 
                        $record->status === 'pending' && 
                        $record->team->owner_id === Auth::id()
                    ),
                    
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (JoinRequest $record): bool => 
                        $record->team->owner_id === Auth::id() || 
                        $record->user_id === Auth::id()
                    ),
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
            'index' => Pages\ListJoinRequests::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $user = Auth::user();

        if ($user->hasRole('admin')) {
            return parent::getEloquentQuery()
                ->with(['user:id,name,email', 'team:id,name,owner_id'])
                ->latest();
        }

        // عرض الطلبات المرسلة للفرق التي يملكها المستخدم أو الطلبات التي أرسلها
        return parent::getEloquentQuery()
            ->select(['id', 'user_id', 'team_id', 'status', 'created_at', 'updated_at'])
            ->where(function ($query) use ($user) {
                $query->whereHas('team', function ($q) use ($user) {
                    $q->where('owner_id', $user->id);
                })->orWhere('user_id', $user->id);
            })
            ->with(['user:id,name,email', 'team:id,name,owner_id'])
            ->latest();
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
