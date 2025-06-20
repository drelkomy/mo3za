<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InvitationResource\Pages;
use App\Models\Invitation;
use App\Jobs\SendInvitationJob;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

class InvitationResource extends Resource
{
    protected static ?string $model = Invitation::class;
    protected static ?string $navigationGroup = 'إدارة الفرق';
    protected static ?string $navigationIcon = 'heroicon-o-envelope';
    protected static ?string $navigationLabel = 'الدعوات';
    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasActiveSubscription() || auth()->user()?->hasRole('admin');
    }
    
    public static function canCreate(): bool
    {
        return false; // إنشاء الدعوات يتم من صفحة الفرق
    }
    
    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasActiveSubscription() || auth()->user()?->hasRole('admin');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        
        // الأدمن يرى جميع الدعوات
        if (auth()->user()?->hasRole('admin')) {
            return $query;
        }
        
        // العضو يرى دعواته فقط
        return $query->where('sender_id', auth()->id());
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('email')
                ->label('البريد الإلكتروني')
                ->email()
                ->required()
                ->maxLength(255),
                
            Forms\Components\Select::make('team_id')
                ->label('الفريق')
                ->relationship('team', 'name')
                ->required(),
                
            Forms\Components\Select::make('status')
                ->label('الحالة')
                ->options([
                    'pending' => 'قيد الانتظار',
                    'accepted' => 'مقبولة',
                    'rejected' => 'مرفوضة',
                ])
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('email')
                    ->label('البريد الإلكتروني')
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('team.name')
                    ->label('الفريق')
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'قيد الانتظار',
                        'accepted' => 'مقبولة',
                        'rejected' => 'مرفوضة',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'accepted' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('accepted_at')
                    ->label('تاريخ القبول')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('الحالة')
                    ->options([
                        'pending' => 'قيد الانتظار',
                        'accepted' => 'مقبولة',
                        'rejected' => 'مرفوضة',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('resend')
                    ->label('إعادة الإرسال')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->action(function (Invitation $record) {
                        // تسجيل إعادة الإرسال
                        Log::info('Resending invitation', [
                            'invitation_id' => $record->id,
                            'email' => $record->email,
                        ]);
                        
                        // إعادة تعيين حالة الدعوة
                        $record->update(['status' => 'pending']);
                        
                        // إرسال الدعوة مرة أخرى
                        SendInvitationJob::dispatch($record);
                        
                        // إظهار رسالة نجاح
                        \Filament\Notifications\Notification::make()
                            ->title('تم إعادة إرسال الدعوة')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (Invitation $record) => $record->status !== 'accepted'),
                    
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (Invitation $record) => $record->status !== 'accepted'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvitations::route('/'),
        ];
    }
}