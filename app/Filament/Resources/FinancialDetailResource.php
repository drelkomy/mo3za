<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FinancialDetailResource\Pages;
use App\Models\FinancialDetail;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FinancialDetailResource extends Resource
{
    protected static ?string $model = FinancialDetail::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationGroup = null; // يظهر مباشرة في القائمة الرئيسية

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return 'الدعم الفني';
    }

    public static function getModelLabel(): string
    {
        return 'دعم فني';
    }

    public static function getPluralModelLabel(): string
    {
        return 'الدعم الفني';
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('admin');
    }

    public static function canCreate(): bool
    {
        // Allow creation only if no record exists to enforce single record policy
        return auth()->user()?->hasRole('admin') && FinancialDetail::count() === 0;
    }
    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->hasRole('admin');
    }
    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false; // لا يمكن الحذف
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
        return $form
            ->schema([
                Forms\Components\Section::make('بيانات الدعم الفني')
                    ->description('هذه البيانات هي الوحيدة التي يتم عرضها لجميع المستخدمين ولا ترتبط بأي شخص محدد. يمكن للأدمن فقط تعديلها.')
                    ->schema([
                        Forms\Components\TextInput::make('whatsapp_number')
                            ->label('رقم الواتساب')
                            ->tel(),
                        Forms\Components\TextInput::make('phone_number')
                            ->label('رقم الهاتف')
                            ->tel(),
                        Forms\Components\TextInput::make('email')
                            ->label('البريد الإلكتروني')
                            ->email(),
                        Forms\Components\Textarea::make('bank_account_details')
                            ->label('تفاصيل الحساب البنكي')
                            ->rows(3),
                        Forms\Components\Hidden::make('user_id')
                            ->default(null) // Ensure no user is linked to this record
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        // عرض نموذج التعديل مباشرة في الصفحة الرئيسية (inline form)
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('whatsapp_number')
                    ->label('رقم الواتساب')
                    ->extraAttributes(['style' => 'font-weight:bold']),
                Tables\Columns\TextColumn::make('phone_number')
                    ->label('رقم الهاتف'),
                Tables\Columns\TextColumn::make('email')
                    ->label('البريد الإلكتروني'),
                Tables\Columns\TextColumn::make('bank_account_details')
                    ->label('تفاصيل الحساب البنكي'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('تعديل')
                    ->slideOver()
            ])
            ->bulkActions([])
            ->contentGrid([
                'md' => 2,
                'xl' => 2,
            ]);
    }



    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFinancialDetails::route('/'),
            'create' => Pages\CreateFinancialDetail::route('/create'),
            'edit' => Pages\EditFinancialDetail::route('/{record}/edit'),
        ];
    }
}
