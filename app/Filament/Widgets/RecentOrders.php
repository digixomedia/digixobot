<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentOrders extends TableWidget
{
    protected static ?string $heading = 'Recent orders';

    public function table(Table $table): Table
    {
        return $table->query(Order::query()->with('customer')->latest()->limit(8))->columns([
            TextColumn::make('order_number')->label('Order'),
            TextColumn::make('customer.customer_number')->label('Customer'),
            TextColumn::make('status')->badge(),
            TextColumn::make('total_paise')->label('Total')->formatStateUsing(fn ($state) => '₹'.number_format($state / 100, 2)),
            TextColumn::make('created_at')->since(),
        ]);
    }
}
