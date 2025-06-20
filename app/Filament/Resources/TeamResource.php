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
        return auth()->user()?->hasActiveSubscription() || auth()->user()?->hasRole('admin');
    }
    
    public static function canCreate(): bool
    {
        $user = auth()->user();
        if ($user?->hasRole('admin')) return false; // إخفاء زر الإضافة للأدمن
        
        if ($user?->hasActiveSubscription()) {
            return $user->ownedTeams()->count() === 0;
        }
        
        return false;
    }
    
    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasActiveSubscription() || auth()->user()?->hasRole('admin');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        
        // الأدمن يرى جميع الفرق
        if (auth()->user()?->hasRole('admin')) {
            return $query;
        }
        
        // العضو يرى فريقه فقط (كمالك فقط)
        return $query->where('owner_id', auth()->id());
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('اسم الفريق')->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('اسم الفريق')->searchable(),
                Tables\Columns\TextColumn::make('owner.name')->label('المالك'),
                Tables\Columns\TextColumn::make('members_count')->label('عدد الأعضاء')->counts('members'),
                Tables\Columns\TextColumn::make('created_at')->label('تاريخ الإنشاء')->dateTime(),
            ])
            ->headerActions([
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
                            $invitation = \App\Models\Invitation::createAndSend(
                                $team->id,
                                auth()->id(),
                                $data['email']
                            );
                        }
                    })
                    ->visible(fn() => auth()->user()->ownedTeams()->exists() && !auth()->user()?->hasRole('admin')),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn(Team $record) => $record->owner_id === auth()->id()),
                
                Tables\Actions\ViewAction::make()
                    ->label('عرض الأعضاء'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTeams::route('/'),
            'create' => Pages\CreateTeam::route('/create'),
            'edit' => Pages\EditTeam::route('/{record}/edit'),
            'view' => Pages\ViewTeam::route('/{record}'),
        ];
    }
}