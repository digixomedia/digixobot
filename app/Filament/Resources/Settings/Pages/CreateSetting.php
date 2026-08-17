<?php
namespace App\Filament\Resources\Settings\Pages;
use App\Filament\Resources\Settings\SettingResource; use App\Services\SettingService; use Filament\Resources\Pages\CreateRecord;
class CreateSetting extends CreateRecord { protected static string $resource = SettingResource::class; protected function afterCreate(): void { app(SettingService::class)->forget($this->record->key); } }
