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
                    ->numeric(),
                TextInput::make('telegram_username')
                    ->tel()
                    ->default(null),
                TextInput::make('name')
                    ->default(null),
                TextInput::make('customer_number')
                    ->required(),
                TextInput::make('wallet_balance_paise')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('total_spend_paise')
                    ->required()
                    ->numeric()
                    ->default(0),
                Textarea::make('internal_notes')
                    ->default(null)
                    ->columnSpanFull(),
                DateTimePicker::make('last_activity_at'),
            ]);
    }
}
