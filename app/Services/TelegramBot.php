<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class TelegramBot
{
    public function sendMessage(int $chatId, string $text, array $keyboard = []): void
    {
        $payload = ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'HTML', 'disable_web_page_preview' => true];
        if ($keyboard !== []) {
            $payload['reply_markup'] = ['inline_keyboard' => $keyboard];
        }

        Http::timeout(10)->post('https://api.telegram.org/bot'.config('services.telegram.bot_token').'/sendMessage', $payload)->throw();
    }

    public function answerCallback(string $callbackId): void
    {
        Http::timeout(10)->post('https://api.telegram.org/bot'.config('services.telegram.bot_token').'/answerCallbackQuery', ['callback_query_id' => $callbackId])->throw();
    }

    public static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
