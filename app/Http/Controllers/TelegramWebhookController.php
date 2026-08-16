<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request): Response
    {
        abort_unless(
            hash_equals((string) config('services.telegram.webhook_secret'), (string) $request->header('X-Telegram-Bot-Api-Secret-Token')),
            403,
        );

        $updateId = $request->integer('update_id');
        abort_unless($updateId > 0, 422);

        try {
            DB::table('telegram_updates')->insert(['update_id' => $updateId, 'created_at' => now(), 'updated_at' => now()]);
        } catch (\Illuminate\Database\QueryException $exception) {
            return response()->noContent(); // Telegram retries are deliberately idempotent.
        }

        // Message and callback dispatch will be added here; acknowledge promptly to avoid Telegram retries.
        DB::table('telegram_updates')->where('update_id', $updateId)->update(['processed_at' => now()]);
        return response()->noContent();
    }
}
