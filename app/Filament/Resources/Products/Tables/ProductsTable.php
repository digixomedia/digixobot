<?php

namespace App\Filament\Resources\Products\Tables;

use App\Models\Product;
use App\Services\TelegramBot;
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
                Action::make('previewTelegram')
                    ->label('Telegram Preview')
                    ->action(function (Product $record): void {
                        $plans = $record->plans()->where('is_active', true)->orderBy('display_order')->get();
                        $text = '<b>'.TelegramBot::escape($record->name).'</b>\n<blockquote>'.TelegramBot::escape($record->description ?: 'Digital product.').'</blockquote>\n\n'
                            .$plans->map(fn ($plan) => '• '.TelegramBot::escape($plan->name).' · '.TelegramBot::escape($plan->validity).' · ₹'.number_format($plan->price_paise / 100, 2))->implode("\n");
                        app(TelegramBot::class)->notifyAdmin($text);
                    }),
                EditAction::make(),
            ])
            ;
    }
}
