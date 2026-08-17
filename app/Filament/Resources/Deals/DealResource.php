<?php

namespace App\Filament\Resources\Deals;

use App\Filament\Resources\Deals\Pages\CreateDeal;
use App\Filament\Resources\Deals\Pages\EditDeal;
use App\Filament\Resources\Deals\Pages\ListDeals;
use App\Models\Deal;
use BackedEnum;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\EditAction;
use Filament\Tables\Table;

class DealResource extends Resource
{
    protected static ?string $model = Deal::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    public static function form(Schema $schema): Schema { return $schema->components([
        Select::make('plan_id')->relationship('plan', 'name')->searchable()->preload()->required(),
        TextInput::make('title')->required()->maxLength(255),
        TextInput::make('deal_price_paise')->label('Deal price (paise)')->numeric()->minValue(1)->required(),
        DateTimePicker::make('starts_at'), DateTimePicker::make('ends_at')->after('starts_at'), Toggle::make('is_active')->default(true),
    ]); }
    public static function table(Table $table): Table { return $table->columns([
        TextColumn::make('title')->searchable(), TextColumn::make('plan.name')->label('Plan'),
        TextColumn::make('deal_price_paise')->label('Price')->formatStateUsing(fn ($state) => '₹'.number_format($state / 100, 2)),
        TextColumn::make('starts_at')->dateTime(), TextColumn::make('ends_at')->dateTime(), IconColumn::make('is_active')->boolean(),
    ])->recordActions([EditAction::make()])->defaultSort('created_at', 'desc'); }
    public static function getPages(): array { return ['index' => ListDeals::route('/'), 'create' => CreateDeal::route('/create'), 'edit' => EditDeal::route('/{record}/edit')]; }
}
