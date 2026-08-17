<?php
namespace App\Filament\Resources\SupportRequests\Pages;
use App\Filament\Resources\SupportRequests\SupportRequestResource;
use Filament\Resources\Pages\EditRecord;
class EditSupportRequest extends EditRecord { protected static string $resource = SupportRequestResource::class; protected function getHeaderActions(): array { return []; } }
