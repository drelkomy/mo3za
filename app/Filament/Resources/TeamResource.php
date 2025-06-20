<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TeamResource\Pages;
use App\Models\Team;
use App\Jobs\SendInvitationJob;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TeamResource extends Resource
{
    protected static ?string $model = Team::class;
    protected static ?string $navigationGroup = 'إدارة الفرق';
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = 'الفرق';
    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        return auth()->check();
    }
    
    public static function canCreate(): bool
    {
        $user = auth()->user();
        if ($user?->hasRole('admin')) return true;
        
        if ($user?->hasActiveSubscription()) {
            return $user->ownedTeams()->count() === 0;
        }
        
        return false;
    }
    
    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        $user = auth()->user();
        // يمكن للمالك أو أعضاء الفريق تعديل اسم الفريق
        return $record->owner_id === $user->id || 
               $record->members()->where('user_id', $user->id)->exists();
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
        return auth()->check();
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        
        if (!auth()->user()?->hasRole('admin')) {
            // عرض الفرق التي يملكها المستخدم فقط
            $query->where('owner_id', auth()->id());
        }
        
        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('اسم الفريق')
                ->required()
                ->maxLength(255),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('اسم الفريق')->searchable(),
                Tables\Columns\TextColumn::make('owner.name')->label('المالك'),
                Tables\Columns\TextColumn::make('members_count')->label('عدد الأعضاء')->counts('members'),
                Tables\Columns\TextColumn::make('members_list')
                    ->label('الأعضاء')
                    ->formatStateUsing(function (Team $record): string {
                        return $record->members->pluck('name')->join('، ');
                    })
                    ->wrap(),
                Tables\Columns\TextColumn::make('created_at')->label('تاريخ الإنشاء')->dateTime(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('add_member')
                    ->label('إضافة عضو')
                    ->icon('heroicon-o-user-plus')
                    ->color('success')
                    ->form([
                        Forms\Components\TextInput::make('name')
                            ->label('الاسم')
                            ->required(),
                        Forms\Components\TextInput::make('email')
                            ->label('البريد الإلكتروني')
                            ->email()
                            ->required(),
                        Forms\Components\TextInput::make('password')
                            ->label('كلمة المرور')
                            ->password()
                            ->required()
                            ->minLength(8),
                    ])
                    ->action(function (array $data) {
                        $team = auth()->user()->ownedTeams()->first();
                        if ($team) {
                            // إنشاء المستخدم
                            $user = \App\Models\User::create([
                                'name' => $data['name'],
                                'email' => $data['email'],
                                'password' => \Hash::make($data['password']),
                                'email_verified_at' => now(),
                            ]);
                            
                            // إعطاء دور عضو
                            $user->assignRole('member');
                            
                            // إضافة للفريق
                            $team->members()->attach($user->id);
                            
                            // الاشتراك التجريبي سيتم إنشاؤه تلقائياً عبر User::boot()
                            
                            \Filament\Notifications\Notification::make()
                                ->title('تم إضافة العضو بنجاح')
                                ->success()
                                ->send();
                        }
                    })
                    ->visible(fn() => auth()->user()->ownedTeams()->exists()),
                
                Tables\Actions\Action::make('invite')
                    ->label('إرسال دعوة')
                    ->icon('heroicon-o-envelope')
                    ->form([
                        Forms\Components\TextInput::make('email')
                            ->label('البريد الإلكتروني')
                            ->email()
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $team = auth()->user()->ownedTeams()->first();
                        if ($team) {
                            $invitation = \App\Models\Invitation::create([
                                'team_id' => $team->id,
                                'email' => $data['email'],
                                'sender_id' => auth()->id(),
                                'token' => \Str::random(32),
                                'status' => 'pending',
                            ]);
                            
                            SendInvitationJob::dispatch($invitation);
                        }
                    })
                    ->visible(fn() => auth()->user()->ownedTeams()->exists()),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(function (Team $record): bool {
                        $user = auth()->user();
                        return $record->owner_id === $user->id || 
                               $record->members()->where('user_id', $user->id)->exists();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTeams::route('/'),
            'create' => Pages\CreateTeam::route('/create'),
            'edit' => Pages\EditTeam::route('/{record}/edit'),
        ];
    }
}