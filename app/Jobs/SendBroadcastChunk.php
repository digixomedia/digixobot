<?php

namespace App\Jobs;

use App\Models\Broadcast;
use App\Services\TelegramBot;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class SendBroadcastChunk implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $broadcastId, public int $afterCustomerId = 0) {}

    public function handle(TelegramBot $bot): void
    {
        $broadcast = Broadcast::find($this->broadcastId);
        if (! $broadcast || ! in_array($broadcast->status, ['queued', 'sending'], true)) { return; }

        $broadcast->update(['status' => 'sending']);
        $customers = DB::table('customers')->where('id', '>', $this->afterCustomerId)->orderBy('id')->limit(20)->get();
        foreach ($customers as $customer) {
            try {
                $bot->sendMessage($customer->telegram_id, '<b>'.TelegramBot::escape($broadcast->title).'</b>\n\n'.TelegramBot::escape($broadcast->message));
                $broadcast->increment('sent_count');
            } catch (\Throwable) {
                // One blocked chat must not stop delivery to all other customers.
            }
        }

        if ($customers->count() === 20) {
            self::dispatch($broadcast->id, (int) $customers->last()->id);
        } else {
            $broadcast->update(['status' => 'sent', 'sent_at' => now()]);
        }
    }
}
