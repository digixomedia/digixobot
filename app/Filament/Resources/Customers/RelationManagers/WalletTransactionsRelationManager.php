<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WalletTransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'walletTransactions';
    protected static ?string $title = 'Wallet history';

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('type')->badge(),
            TextColumn::make('amount_paise')->label('Amount')->formatStateUsing(fn ($state) => ($state >= 0 ? '+' : '−').'₹'.number_format(abs($state) / 100, 2)),
            TextColumn::make('balance_after_paise')->label('Balance after')->formatStateUsing(fn ($state) => '₹'.number_format($state / 100, 2)),
            TextColumn::make('reference'),
            TextColumn::make('created_at')->dateTime(),
        ])->defaultSort('created_at', 'desc');
    }
}
