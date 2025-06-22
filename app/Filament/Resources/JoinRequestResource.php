<?php

namespace App\Filament\Resources;

use App\Filament\Resources\JoinRequestResource\Pages;
use App\Models\JoinRequest;
use App\Models\Team;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

use App\Models\User;
use Filament\Notifications\Notification as FilamentNotification;

class JoinRequestResource extends Resource
{
    protected static ?string $model = JoinRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-plus';

    protected static ?string $navigationGroup = null;

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'طلبات الانضمام';

    protected static ?string $modelLabel = 'طلب انضمام';

    protected static ?string $pluralModelLabel = 'طلبات الانضمام';

    public static function canViewResource(Model $record = null): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        // Show to all users who can view join requests
        return $user->can('view join requests');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check(); // إظهار دائماً للمستخدمين المسجلين
    }

    

    public static function getNavigationBadge(): ?string
    {
        // Reduce duplicate DB cache lookups within the same HTTP request
        static $cachedCount = null;
        if (! is_null($cachedCount)) {
            return (string) $cachedCount;
        }

        $userId = Auth::id() ?? 'guest';
        $cachedCount = Cache::remember("pending_join_requests_count_{$userId}", now()->addMinutes(5), function () {
            return parent::getEloquentQuery()->where('status', 'pending')->count();
        });

        return (string) $cachedCount;
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
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true), // Hide by default
            ])
            ->actions([
                Tables\Actions\Action::make('accept')
                    ->label('موافقة')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (JoinRequest $record) {
                        $team = $record->team;
                        $user = $record->user;

                        $team->members()->attach($user->id);
                        $record->update(['status' => 'accepted']);

                        FilamentNotification::make()
                            ->title('تم قبول الطلب')
                            ->body("تم قبولك في فريق {$team->name}")
                            ->success()
                            ->send();
                    })
                    ->visible(fn (JoinRequest $record): bool => 
                        $record->status === 'pending' && $record->user_id === Auth::id()
                    ),

                Tables\Actions\Action::make('reject')
                    ->label('رفض')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (JoinRequest $record) {
                        $record->update(['status' => 'rejected']);
                        
                        FilamentNotification::make()
                            ->title('تم رفض الطلب')
                            ->warning()
                            ->send();
                    })
                    ->visible(fn (JoinRequest $record): bool => 
                        $record->status === 'pending' && $record->user_id === Auth::id()
                    ),
                    
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (JoinRequest $record): bool => 
                        $record->user_id === Auth::id() || $record->team->owner_id === Auth::id()
                    ),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->paginationPageOptions([5])
            ->defaultPaginationPageOption(5);
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
                ->latest();
        }

        // عرض الطلبات للمدعو ومرسل الدعوة
        return parent::getEloquentQuery()
            ->where(function($query) use ($user) {
                $query->where('user_id', $user->id) // الطلبات المرسلة للمستخدم
                      ->orWhereHas('team', function($q) use ($user) {
                          $q->where('owner_id', $user->id); // الطلبات التي أرسلها قائد الفريق
                      });
            })
            ->latest();
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
