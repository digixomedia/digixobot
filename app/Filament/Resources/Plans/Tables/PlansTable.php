<?php

namespace App\Filament\Resources\Plans\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PlansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product.name')
                    ->label('Product')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('validity')
                    ->searchable(),
                TextColumn::make('price_paise')
                    ->label('Price')
                    ->formatStateUsing(fn ($state) => '₹'.number_format($state / 100, 2))
                    ->sortable(),
                TextColumn::make('compare_at_price_paise')
                    ->label('Compare at')
                    ->formatStateUsing(fn ($state) => $state ? '₹'.number_format($state / 100, 2) : '—')
                    ->sortable(),
                TextColumn::make('stock')
                    ->placeholder('Untracked')
                    ->formatStateUsing(fn ($state) => $state === null ? 'Untracked' : number_format($state))
                    ->sortable(),
                TextColumn::make('delivery_method')
                    ->searchable(),
                TextColumn::make('delivery_estimate')
                    ->searchable(),
                TextColumn::make('activation_method')
                    ->searchable(),
                TextColumn::make('warranty')
                    ->searchable(),
                IconColumn::make('is_active')
                    ->boolean(),
                TextColumn::make('display_order')
                    ->numeric()
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
                EditAction::make(),
            ])
            ;
    }
}
