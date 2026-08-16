<?php

namespace App\Filament\Resources\WalletTransactions\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class WalletTransactionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('customer_id')
                    ->required()
                    ->numeric(),
                TextInput::make('order_id')
                    ->numeric()
                    ->default(null),
                TextInput::make('type')
                    ->required(),
                TextInput::make('amount_paise')
                    ->required()
                    ->numeric(),
                TextInput::make('balance_after_paise')
                    ->required()
                    ->numeric(),
                TextInput::make('reference')
                    ->default(null),
                TextInput::make('idempotency_key')
                    ->required(),
                Textarea::make('note')
                    ->default(null)
                    ->columnSpanFull(),
                TextInput::make('created_by')
                    ->numeric()
                    ->default(null),
            ]);
    }
}
