<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use Filament\Resources\Pages\EditRecord;
use App\Services\TelegramBot;
use Illuminate\Support\Facades\DB;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected function afterSave(): void
    {
        if (! $this->record->wasChanged('status')) { return; }
        if ($this->record->status === 'delivered' && ! $this->record->delivered_at) {
            $this->record->updateQuietly(['delivered_at' => now()]);
        }
        $customer = DB::table('customers')->find($this->record->customer_id);
        DB::table('audit_logs')->insert([
            'user_id' => auth()->id(), 'action' => 'order.status_changed',
            'auditable_type' => get_class($this->record), 'auditable_id' => $this->record->id,
            'after' => json_encode(['status' => $this->record->status]),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        if ($customer) {
            app(TelegramBot::class)->sendMessage($customer->telegram_id,
                '<b>Order updated</b>\n\nOrder: <code>'.TelegramBot::escape($this->record->order_number).'</code>\nStatus: <b>'.TelegramBot::escape(ucfirst($this->record->status)).'</b>'
            );
        }
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
