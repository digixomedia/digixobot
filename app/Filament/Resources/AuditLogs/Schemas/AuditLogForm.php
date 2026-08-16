<?php

namespace App\Filament\Resources\AuditLogs\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class AuditLogForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('user_id')
                    ->numeric()
                    ->default(null),
                TextInput::make('action')
                    ->required(),
                TextInput::make('auditable_type')
                    ->default(null),
                TextInput::make('auditable_id')
                    ->numeric()
                    ->default(null),
                Textarea::make('before')
                    ->default(null)
                    ->columnSpanFull(),
                Textarea::make('after')
                    ->default(null)
                    ->columnSpanFull(),
                TextInput::make('ip_address')
                    ->default(null),
            ]);
    }
}
