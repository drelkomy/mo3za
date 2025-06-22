<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TeamResource\Pages;
use App\Models\Team;
use App\Models\User;
use App\Models\JoinRequest;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;

class TeamResource extends Resource
{
    protected static ?string $model = Team::class;
    protected static ?string $navigationGroup = 'إدارة الفرق';
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = 'الفرق';
    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }
        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->canAddTeamMembers();
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();
        if (!$user || $user->hasRole('admin')) {
            return false;
        }

        // User must have a valid subscription and not own any teams yet.
        return $user->canAddTeamMembers() && $user->ownedTeams()->count() === 0;
    }

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }
        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->canAddTeamMembers();
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['owner', 'members']);

        // إذا لم يكن المستخدم أدمن، قيّد النتائج على فرقه فقط
        if (! auth()->user()?->hasRole('admin')) {
            $query->where('owner_id', auth()->id());
        }

        return $query;
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
                Tables\Actions\Action::make('inviteMember')
                    ->label('إرسال طلب انضمام')
                    ->icon('heroicon-o-user-plus')
                    ->form([
                        Forms\Components\TextInput::make('email')
                            ->label('البريد الإلكتروني للعضو')
                            ->email()
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $owner = Auth::user();
                        $team = $owner->ownedTeams()->first();

                        if (!$team) {
                            Notification::make()->title('خطأ')->body('لا تملك فريقًا لدعوة أعضاء إليه.')->danger()->send();
                            return;
                        }

                        $userToInvite = User::where('email', $data['email'])->first();

                        if (!$userToInvite) {
                            Notification::make()->title('المستخدم غير موجود')->body('لا يوجد مستخدم مسجل بهذا البريد الإلكتروني.')->danger()->send();
                            return;
                        }

                        if ($team->hasMember($userToInvite->id) || $team->owner_id === $userToInvite->id) {
                            Notification::make()->title('عضو بالفعل')->body('هذا المستخدم عضو بالفعل في الفريق.')->warning()->send();
                            return;
                        }

                        $existingRequest = JoinRequest::where('user_id', $userToInvite->id)
                            ->where('team_id', $team->id)
                            ->first();

                        if ($existingRequest) {
                            // إذا كان الطلب مرفوضاً، يمكن تحديثه
                            if ($existingRequest->status === 'rejected') {
                                $existingRequest->update(['status' => 'pending']);
                                Notification::make()->title('تم إرسال الطلب بنجاح')->body("تم إرسال طلب انضمام إلى {$userToInvite->name}.")->success()->send();
                                return;
                            }
                            
                            $status = match($existingRequest->status) {
                                'pending' => 'معلق',
                                'accepted' => 'مقبول',
                                default => $existingRequest->status
                            };
                            
                            Notification::make()->title('طلب موجود')->body("يوجد طلب انضمام {$status} بالفعل لهذا المستخدم.")->warning()->send();
                            return;
                        }

                        // إنشاء طلب جديد
                        JoinRequest::create([
                            'user_id' => $userToInvite->id,
                            'team_id' => $team->id,
                            'status' => 'pending',
                        ]);

                        Notification::make()->title('تم إرسال الطلب بنجاح')->body("تم إرسال طلب انضمام إلى {$userToInvite->name}.")->success()->send();
                    })
                    ->visible(fn (): bool => auth()->user()->canAddTeamMembers())
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ViewAction::make()->label('عرض الأعضاء'),
            ])
            ->bulkActions([])
            ->paginationPageOptions([5])
            ->defaultPaginationPageOption(5);
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