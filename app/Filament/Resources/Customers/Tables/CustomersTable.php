<?php

namespace App\Filament\Resources\Customers\Tables;

use App\Models\Customer;
use App\Services\WalletService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('telegram_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('telegram_username')
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('customer_number')
                    ->searchable(),
                TextColumn::make('wallet_balance_paise')
                    ->label('Wallet balance')
                    ->formatStateUsing(fn ($state) => '₹'.number_format($state / 100, 2))
                    ->sortable(),
                TextColumn::make('total_spend_paise')
                    ->label('Total spending')
                    ->formatStateUsing(fn ($state) => '₹'.number_format($state / 100, 2))
                    ->sortable(),
                TextColumn::make('last_activity_at')
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
                Action::make('creditWallet')
                    ->label('Add Wallet Credit')
                    ->schema([
                        TextInput::make('amount_rupees')->required()->numeric()->minValue(0.01),
                        TextInput::make('reference')->required()->maxLength(255),
                    ])
                    ->requiresConfirmation()
                    ->action(fn (Customer $record, array $data) => app(WalletService::class)->credit(
                        $record,
                        (int) round(((float) $data['amount_rupees']) * 100),
                        $data['reference'],
                        auth()->id(),
                    )),
                EditAction::make(),
            ]);
    }
}
