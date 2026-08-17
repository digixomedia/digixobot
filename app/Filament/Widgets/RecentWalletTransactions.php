<?php

namespace App\Filament\Widgets;

use App\Models\WalletTransaction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentWalletTransactions extends TableWidget
{
    protected static ?string $heading = 'Recent wallet transactions';

    public function table(Table $table): Table
    {
        return $table->query(WalletTransaction::query()->with('customer')->latest()->limit(8))->columns([
            TextColumn::make('customer.customer_number')->label('Customer'),
            TextColumn::make('type')->badge(),
            TextColumn::make('amount_paise')->label('Amount')->formatStateUsing(fn ($state) => ($state >= 0 ? '+' : '−').'₹'.number_format(abs($state) / 100, 2)),
            TextColumn::make('reference')->limit(30),
            TextColumn::make('created_at')->since(),
        ]);
    }
}
