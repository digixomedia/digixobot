<?php

namespace App\Filament\Resources\Products\Tables;

use App\Models\Product;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('category.name')
                    ->label('Category')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('slug')
                    ->searchable(),
                IconColumn::make('is_active')
                    ->boolean(),
                IconColumn::make('is_featured')
                    ->boolean(),
                IconColumn::make('is_deal')
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
                Action::make('duplicate')
                    ->action(function (Product $record): void {
                        $copy = $record->replicate();
                        $copy->name = $record->name.' Copy';
                        $copy->slug = $record->slug.'-copy-'.str()->lower(str()->random(5));
                        $copy->is_active = false;
                        $copy->save();
                        foreach ($record->plans as $plan) {
                            $planCopy = $plan->replicate();
                            $planCopy->product_id = $copy->id;
                            $planCopy->is_active = false;
                            $planCopy->save();
                        }
                    }),
                EditAction::make(),
            ])
            ;
    }
}
