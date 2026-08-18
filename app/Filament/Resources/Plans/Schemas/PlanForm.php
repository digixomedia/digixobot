<?php

namespace App\Filament\Resources\Plans\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

class PlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('product_id')
                    ->relationship('product', 'name')
                    ->searchable()->preload()->required(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('validity')
                    ->required(),
                TextInput::make('price_paise')
                    ->required()
                    ->numeric(),
                TextInput::make('compare_at_price_paise')
                    ->numeric()
                    ->default(null),
                TextInput::make('stock')
                    ->label('Stock (blank = untracked)')
                    ->nullable()
                    ->numeric()
                    ->minValue(0)
                    ->default(null),
                TextInput::make('delivery_method')
                    ->default(null),
                TextInput::make('delivery_estimate')
                    ->default(null),
                TextInput::make('activation_method')
                    ->default(null),
                TextInput::make('warranty')
                    ->default(null),
                Textarea::make('conditions')
                    ->default(null)
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->required(),
                TextInput::make('display_order')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }
}
