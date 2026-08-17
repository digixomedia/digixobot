<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';
    protected static ?string $title = 'Order history';

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('order_number')->label('Order'),
            TextColumn::make('status')->badge(),
            TextColumn::make('total_paise')->label('Total')->formatStateUsing(fn ($state) => '₹'.number_format($state / 100, 2)),
            TextColumn::make('created_at')->dateTime(),
        ])->defaultSort('created_at', 'desc');
    }
}
