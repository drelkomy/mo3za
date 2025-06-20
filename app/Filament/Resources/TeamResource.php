<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TeamResource\Pages;
use App\Models\Team;
use App\Jobs\SendInvitationJob;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

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
                        if (!$team) {
                            return;
                        }
                        
                        // التحقق من وجود المستخدم
                        $existingUser = User::where('email', $data['email'])->first();
                        
                        // التحقق من وجود دعوة سابقة
                        $existingInvitation = \App\Models\Invitation::where('team_id', $team->id)
                            ->where('email', $data['email'])
                            ->where('status', 'pending')
                            ->first();
                        
                        if ($existingInvitation) {
                            // إظهار رسالة خطأ إذا كانت هناك دعوة سابقة
                            \Filament\Notifications\Notification::make()
                                ->title('الدعوة موجودة بالفعل')
                                ->body("تم إرسال دعوة لهذا البريد الإلكتروني مسبقاً")
                                ->danger()
                                ->send();
                            return;
                        }
                        
                        // إنشاء الدعوة
                        $invitation = \App\Models\Invitation::create([
                            'team_id' => $team->id,
                            'sender_id' => auth()->id(),
                            'email' => $data['email'],
                            'token' => \Illuminate\Support\Str::random(32),
                            'status' => 'pending',
                        ]);
                        
                        // تسجيل إنشاء الدعوة
                        Log::info('Invitation created from TeamResource', [
                            'invitation_id' => $invitation->id,
                            'email' => $invitation->email,
                            'team_id' => $team->id,
                        ]);
                        
                        if ($existingUser) {
                            // إرسال إشعار فقط للمستخدمين الموجودين
                            Log::info('User already exists, sending notification only', [
                                'user_id' => $existingUser->id,
                                'email' => $existingUser->email,
                            ]);
                            
                            // إرسال إشعار للمستخدم الموجود
                            \App\Jobs\SendNotificationJob::dispatch(
                                $existingUser,
                                'دعوة انضمام للفريق',
                                "تم دعوتك للانضمام إلى فريق {$team->name}",
                                'info',
                                ['invitation_id' => $invitation->id],
                                url('/invitations/' . $invitation->token)
                            );
                            
                            // إظهار رسالة نجاح
                            \Filament\Notifications\Notification::make()
                                ->title('تم إرسال الدعوة')
                                ->body("تم إرسال إشعار للمستخدم {$data['email']}")
                                ->success()
                                ->send();
                        } else {
                            // إرسال إيميل للمستخدمين الجدد
                            Log::info('New user, sending email invitation', [
                                'email' => $data['email'],
                            ]);
                            
                            // إرسال الدعوة عبر البريد
                            \Illuminate\Support\Facades\Mail::to($data['email'])
                                ->send(new \App\Mail\InvitationMail($invitation));
                            
                            // إظهار رسالة نجاح
                            \Filament\Notifications\Notification::make()
                                ->title('تم إرسال الدعوة')
                                ->body("تم إرسال دعوة بالبريد الإلكتروني إلى {$data['email']}")
                                ->success()
                                ->send();
                        }
                        
                        // إرسال إشعار للمرسل
                        \App\Jobs\SendNotificationJob::dispatch(
                            auth()->user(),
                            'تم إرسال الدعوة',
                            "تم إرسال دعوة انضمام للفريق {$team->name} إلى {$data['email']}",
                            'success'
                        );
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