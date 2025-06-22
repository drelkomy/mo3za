<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use App\Models\City;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?int $navigationSort = 1;

    /**
     * تحسين الأداء بتحميل العلاقات والعدادات مسبقاً
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['area', 'city']);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasRole('admin');
    }

    public static function getNavigationLabel(): string
    {
        return 'الأعضاء';
    }

    public static function getModelLabel(): string
    {
        return 'عضو';
    }

    public static function getPluralModelLabel(): string
    {
        return 'الأعضاء';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('المعلومات الأساسية')
                    ->schema([
                        Forms\Components\FileUpload::make('avatar_url')
                            ->label('الصورة الشخصية')
                            ->avatar()
                            ->image()
                            ->directory('avatars')
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('name')
                            ->label('الاسم')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('email')
                            ->label('البريد الإلكتروني')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('password')
                            ->label('كلمة المرور')
                            ->password()
                            ->required(fn ($livewire) => $livewire instanceof Pages\CreateUser)
                            ->dehydrated(fn ($state) => filled($state))
                            ->dehydrateStateUsing(fn ($state) => bcrypt($state)),
                        Forms\Components\TextInput::make('phone')
                            ->label('رقم الهاتف')
                            ->tel()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Forms\Components\Select::make('user_type')
                            ->label('نوع العضو')
                            ->options([
                                'admin' => 'مدير',
                                'member' => 'عضو',
                            ])
                            ->default('member')
                            ->required()
                            ->native(false)
                            ->disabled(fn ($record) => $record && $record->id === auth()->id()),
                        Forms\Components\Toggle::make('is_active')
                            ->label('نشط')
                            ->default(true),
                    ])->columns(2),

                Forms\Components\Section::make('الموقع الجغرافي')
                    ->schema([
                        Forms\Components\Select::make('area_id')
                            ->label('المنطقة')
                            ->relationship('area', 'name')
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->required(),
                        Forms\Components\Select::make('city_id')
                            ->label('المدينة')
                            ->relationship(
                                name: 'city',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query, callable $get) => $query->where('area_id', $get('area_id')),
                            )
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabled(fn (callable $get) => ! $get('area_id')),
                    ])->columns(2),

                Forms\Components\Section::make('معلومات إضافية')
                    ->schema([
                        Forms\Components\Select::make('gender')
                            ->label('الجنس')
                            ->options([
                                'male' => 'ذكر',
                                'female' => 'أنثى',
                            ]),
                        Forms\Components\DatePicker::make('birthdate')
                            ->label('تاريخ الميلاد'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('avatar')
                    ->label('الصورة')
                    ->circular()
                    ->getStateUsing(fn (User $record): ?string => $record->getFilamentAvatarUrl()),
                Tables\Columns\TextColumn::make('name')
                    ->label('الاسم')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('البريد الإلكتروني')
                    ->searchable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label('رقم الهاتف')
                    ->searchable(),
                Tables\Columns\TextColumn::make('area.name')
                    ->label('المنطقة')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('city.name')
                    ->label('المدينة')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('user_type')
                    ->label('نوع المستخدم')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'admin' => 'success',
                        'member' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn($state) => $state === 'admin' ? 'مدير' : 'عضو'),
               
                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('نشط')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاريخ التسجيل')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('user_type')
                    ->label('نوع المستخدم')
                    ->options([
                        'admin' => 'مدير',
                        'member' => 'عضو',
                    ]),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('نشط'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->paginationPageOptions([5])
            ->defaultPaginationPageOption(5);
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}