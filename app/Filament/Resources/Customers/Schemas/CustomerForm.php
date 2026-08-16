<?php

namespace App\Filament\Resources\Customers\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('telegram_id')
                    ->tel()
                    ->required()
                    ->numeric()
                    ->disabled(),
                TextInput::make('telegram_username')
                    ->tel()
                    ->default(null)
                    ->disabled(),
                TextInput::make('name')
                    ->default(null),
                TextInput::make('customer_number')
                    ->required()
                    ->disabled(),
                TextInput::make('wallet_balance_paise')
                    ->required()
                    ->numeric()
                    ->default(0)
                    ->disabled()
                    ->dehydrated(false),
                TextInput::make('total_spend_paise')
                    ->required()
                    ->numeric()
                    ->default(0)
                    ->disabled()
                    ->dehydrated(false),
                Textarea::make('internal_notes')
                    ->default(null)
                    ->columnSpanFull(),
                DateTimePicker::make('last_activity_at'),
            ]);
    }
}
