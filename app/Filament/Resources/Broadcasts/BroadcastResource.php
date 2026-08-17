<?php

namespace App\Filament\Resources\Broadcasts;

use App\Filament\Resources\Broadcasts\Pages\CreateBroadcast;
use App\Filament\Resources\Broadcasts\Pages\EditBroadcast;
use App\Filament\Resources\Broadcasts\Pages\ListBroadcasts;
use App\Jobs\SendBroadcastChunk;
use App\Models\Broadcast;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BroadcastResource extends Resource
{
    protected static ?string $model = Broadcast::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    public static function form(Schema $schema): Schema { return $schema->components([
        TextInput::make('title')->required()->maxLength(255),
        Textarea::make('message')->required()->maxLength(3500)->rows(10)->columnSpanFull(),
    ]); }
    public static function table(Table $table): Table { return $table->columns([
        TextColumn::make('title')->searchable(), TextColumn::make('status')->badge(), TextColumn::make('sent_count')->numeric(),
        TextColumn::make('sent_at')->dateTime(), TextColumn::make('created_at')->dateTime()->sortable(),
    ])->recordActions([
        Action::make('send')->visible(fn (Broadcast $record) => $record->status === 'draft')->requiresConfirmation()
            ->action(function (Broadcast $record): void { $record->update(['status' => 'queued']); SendBroadcastChunk::dispatch($record->id); }),
        EditAction::make()->visible(fn (Broadcast $record) => $record->status === 'draft'),
    ])->defaultSort('created_at', 'desc'); }
    public static function getPages(): array { return ['index' => ListBroadcasts::route('/'), 'create' => CreateBroadcast::route('/create'), 'edit' => EditBroadcast::route('/{record}/edit')]; }
}
