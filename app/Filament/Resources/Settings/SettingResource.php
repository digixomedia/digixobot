<?php

namespace App\Filament\Resources\Settings;

use App\Filament\Resources\Settings\Pages\CreateSetting;
use App\Filament\Resources\Settings\Pages\EditSetting;
use App\Filament\Resources\Settings\Pages\ListSettings;
use App\Models\Setting;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SettingResource extends Resource
{
    protected static ?string $model = Setting::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    public static function form(Schema $schema): Schema { return $schema->components([
        Select::make('key')->options([
            'support_username' => 'Telegram support username', 'admin_telegram_id' => 'Admin Telegram ID',
            'store_terms' => 'Store terms', 'low_stock_threshold' => 'Low-stock threshold',
        ])->required()->unique(ignoreRecord: true),
        Textarea::make('value')->required()->columnSpanFull(),
    ]); }
    public static function table(Table $table): Table { return $table->columns([
        TextColumn::make('key')->searchable(), TextColumn::make('value')->limit(80), TextColumn::make('updated_at')->dateTime(),
    ])->recordActions([EditAction::make()]); }
    public static function getPages(): array { return ['index' => ListSettings::route('/'), 'create' => CreateSetting::route('/create'), 'edit' => EditSetting::route('/{record}/edit')]; }
}
