<?php

namespace App\Filament\Resources\Orders\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('customer_id')
                    ->required()
                    ->numeric(),
                TextInput::make('order_number')
                    ->required(),
                TextInput::make('status')
                    ->required()
                    ->default('paid'),
                TextInput::make('total_paise')
                    ->required()
                    ->numeric(),
                TextInput::make('purchase_key')
                    ->required(),
                DateTimePicker::make('paid_at'),
                DateTimePicker::make('delivered_at'),
            ]);
    }
}
