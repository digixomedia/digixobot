<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Models\Order;
use App\Services\RefundService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('customer_id')
                    ->label('Customer')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('order_number')
                    ->searchable(),
                TextColumn::make('status')
                    ->searchable(),
                TextColumn::make('total_paise')
                    ->label('Total')
                    ->formatStateUsing(fn ($state) => '₹'.number_format($state / 100, 2))
                    ->sortable(),
                TextColumn::make('purchase_key')
                    ->searchable(),
                TextColumn::make('paid_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('delivered_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('refund')
                    ->color('danger')
                    ->visible(fn (Order $record) => in_array($record->status, ['paid', 'processing', 'delivered'], true))
                    ->schema([Textarea::make('reason')->required()->maxLength(1000)])
                    ->requiresConfirmation()
                    ->action(fn (Order $record, array $data) => app(RefundService::class)->refund($record, $data['reason'], auth()->id())),
                EditAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
