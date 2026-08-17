<?php

namespace App\Filament\Resources\Customers\Tables;

use App\Models\Customer;
use App\Services\WalletService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
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
                TextColumn::make('orders_count')->label('Orders')->numeric()->sortable(),
                TextColumn::make('last_activity_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Registered')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
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
                Action::make('adjustWallet')
                    ->label('Wallet Adjustment')
                    ->schema([
                        Select::make('type')->options(['promotional_credit' => 'Promotional credit', 'admin_correction' => 'Administrative correction'])->required(),
                        TextInput::make('amount_rupees')->helperText('Use a negative amount only for an administrative correction.')->required()->numeric()->notIn([0]),
                        TextInput::make('reference')->required()->maxLength(255),
                    ])
                    ->requiresConfirmation()
                    ->action(fn (Customer $record, array $data) => app(WalletService::class)->adjust(
                        $record, (int) round(((float) $data['amount_rupees']) * 100), $data['type'], $data['reference'], auth()->id(),
                    )),
                EditAction::make(),
            ]);
    }
}
