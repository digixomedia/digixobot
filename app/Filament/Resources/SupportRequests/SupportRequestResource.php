<?php

namespace App\Filament\Resources\SupportRequests;

use App\Filament\Resources\SupportRequests\Pages\EditSupportRequest;
use App\Filament\Resources\SupportRequests\Pages\ListSupportRequests;
use App\Models\SupportRequest;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\EditAction;
use Filament\Tables\Table;

class SupportRequestResource extends Resource
{
    protected static ?string $model = SupportRequest::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('customer_id')->relationship('customer', 'customer_number')->disabled(),
            Textarea::make('message')->disabled()->columnSpanFull(),
            Select::make('status')->options(['open' => 'Open', 'in_progress' => 'In progress', 'resolved' => 'Resolved'])->required(),
            Textarea::make('admin_note')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('customer.customer_number')->label('Customer')->searchable(),
            TextColumn::make('message')->limit(60)->searchable(),
            TextColumn::make('status')->badge(),
            TextColumn::make('created_at')->dateTime()->sortable(),
        ])->recordActions([EditAction::make()])->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ListSupportRequests::route('/'), 'edit' => EditSupportRequest::route('/{record}/edit')];
    }
}
