<?php

namespace App\Filament\Resources\Orders\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('customer_id')
                    ->required()
                    ->numeric()->disabled(),
                TextInput::make('order_number')
                    ->required()->disabled(),
                Select::make('status')->options([
                    'paid' => 'Paid', 'processing' => 'Processing', 'delivered' => 'Delivered',
                    'cancelled' => 'Cancelled', 'refunded' => 'Refunded', 'failed' => 'Failed',
                ])->required(),
                TextInput::make('total_paise')
                    ->required()
                    ->numeric()->disabled(),
                TextInput::make('purchase_key')
                    ->required()->disabled(),
                DateTimePicker::make('paid_at'),
                DateTimePicker::make('delivered_at'),
            ]);
    }
}
