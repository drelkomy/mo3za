<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MyTeamMembersResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MyTeamMembersResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationGroup = 'إدارة الفرق';
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = 'أعضاء فريقي';
    protected static ?int $navigationSort = 4;

    public static function canViewAny(): bool
    {
        return auth()->check();
    }
    
    public static function canCreate(): bool
    {
        return false;
    }
    
    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }
    
    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }
    
    public static function canView(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }
    
    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();
        return $user && ($user->ownedTeams()->exists() || $user->teams()->exists());
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();
        $userIds = collect();
        
        // إضافة أعضاء الفريق الذي يملكه
        $ownedTeam = $user->ownedTeams()->first();
        if ($ownedTeam) {
            $userIds = $userIds->merge($ownedTeam->members()->pluck('users.id'));
        }
        
        // إضافة أعضاء الفرق التي ينتمي إليها + مالك الفريق
        foreach ($user->teams as $team) {
            $userIds->push($team->owner_id);
            $userIds = $userIds->merge($team->members()->pluck('users.id'));
        }
        
        if ($userIds->isEmpty()) {
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }
        
        return parent::getEloquentQuery()->whereIn('id', $userIds->unique()->values());
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('الاسم')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('email')
                    ->label('البريد الإلكتروني')
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('phone')
                    ->label('الهاتف')
                    ->placeholder('غير محدد'),
                
                Tables\Columns\TextColumn::make('team_role')
                    ->label('الدور')
                    ->formatStateUsing(function (User $record): string {
                        $user = auth()->user();
                        
                        // إذا كان مالك فريق
                        if ($user->ownedTeams()->where('owner_id', $record->id)->exists()) {
                            return 'مالك فريق';
                        }
                        
                        // إذا كان هو نفسه مالك فريق يحتوي على هذا العضو
                        $ownedTeam = $user->ownedTeams()->first();
                        if ($ownedTeam && $ownedTeam->members()->where('users.id', $record->id)->exists()) {
                            return 'عضو في فريقي';
                        }
                        
                        // إذا كان زميل في نفس الفريق
                        foreach ($user->teams as $team) {
                            if ($team->owner_id === $record->id) {
                                return 'مالك الفريق';
                            }
                            if ($team->members()->where('users.id', $record->id)->exists()) {
                                return 'زميل في الفريق';
                            }
                        }
                        
                        return 'عضو';
                    })
                    ->badge()
                    ->color(fn (User $record): string => 
                        auth()->user()->ownedTeams()->where('owner_id', $record->id)->exists() ? 'danger' :
                        (auth()->user()->ownedTeams()->first()?->members()->where('users.id', $record->id)->exists() ? 'success' : 'primary')
                    ),
                
                Tables\Columns\IconColumn::make('is_active')
                    ->label('نشط')
                    ->boolean(),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاريخ التسجيل')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('assign_task')
                    ->label('إسناد مهمة')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->color('primary')
                    ->url(fn (User $record): string => route('filament.admin.resources.tasks.create', ['receiver_id' => $record->id]))
                    ->visible(fn (User $record): bool => 
                        auth()->user()->ownedTeams()->exists() && 
                        $record->id !== auth()->id()
                    ),
            ])
            ->emptyStateHeading('لا يوجد أعضاء فريق')
            ->emptyStateDescription('انضم إلى فريق أو أنشئ فريقك الخاص');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMyTeamMembers::route('/'),
        ];
    }
}