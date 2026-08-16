<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Services\TelegramBot;

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

        $message = $request->input('message');
        if (is_array($message) && isset($message['chat']['id'])) {
            $this->handleMessage($message);
        }
        if (is_array($request->input('callback_query'))) {
            $this->handleCallback($request->input('callback_query'));
        }

        DB::table('telegram_updates')->where('update_id', $updateId)->update(['processed_at' => now()]);
        return response()->noContent();
    }

    private function handleMessage(array $message): void
    {
        $chatId = (int) $message['chat']['id'];
        $from = $message['from'] ?? [];
        $text = trim((string) ($message['text'] ?? ''));
        $customer = DB::table('customers')->where('telegram_id', $chatId)->first();

        if (! $customer) {
            $customerId = DB::table('customers')->insertGetId([
                'telegram_id' => $chatId,
                'telegram_username' => $from['username'] ?? null,
                'name' => trim(($from['first_name'] ?? '').' '.($from['last_name'] ?? '')) ?: null,
                'customer_number' => 'DXO-'.str_pad((string) $chatId, 10, '0', STR_PAD_LEFT),
                'last_activity_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            $customer = DB::table('customers')->find($customerId);
        } else {
            DB::table('customers')->where('id', $customer->id)->update([
                'telegram_username' => $from['username'] ?? $customer->telegram_username,
                'name' => trim(($from['first_name'] ?? '').' '.($from['last_name'] ?? '')) ?: $customer->name,
                'last_activity_at' => now(), 'updated_at' => now(),
            ]);
            $customer = DB::table('customers')->find($customer->id);
        }

        if (str_starts_with($text, '/start') || $text === '/shop' || $text === '/categories') {
            app(TelegramBot::class)->sendMessage($chatId, $this->mainMenu($customer), $this->menuKeyboard());
        }
    }

    private function mainMenu(object $customer): string
    {
        $orders = DB::table('orders')->where('customer_id', $customer->id)->count();
        $name = TelegramBot::escape($customer->name ?: 'Customer');
        return "<b>Welcome to DigiXO Store, {$name}</b>\n\n"
            ."<b>Customer ID:</b> <code>{$customer->customer_number}</code>\n"
            ."<b>Wallet balance:</b> ₹".number_format($customer->wallet_balance_paise / 100, 2)."\n"
            ."<b>Orders:</b> {$orders}\n\nChoose an option below.";
    }

    private function menuKeyboard(): array
    {
        return [
            [['text' => '🛍️ Shop Now', 'callback_data' => 'shop'], ['text' => '📂 Categories', 'callback_data' => 'categories']],
            [['text' => '🔍 Search Products', 'callback_data' => 'search'], ['text' => '📦 My Orders', 'callback_data' => 'orders']],
            [['text' => '💰 My Wallet', 'callback_data' => 'wallet'], ['text' => '🎯 Today’s Deals', 'callback_data' => 'deals']],
            [['text' => '👤 My Account', 'callback_data' => 'account'], ['text' => '💬 Help & Support', 'callback_data' => 'support']],
        ];
    }

    private function handleCallback(array $callback): void
    {
        $chatId = (int) data_get($callback, 'message.chat.id');
        $data = (string) ($callback['data'] ?? '');
        $bot = app(TelegramBot::class);
        $bot->answerCallback((string) $callback['id']);
        $customer = DB::table('customers')->where('telegram_id', $chatId)->first();
        if (! $customer) {
            $bot->sendMessage($chatId, 'Please send /start first.');
            return;
        }

        if ($data === 'wallet') {
            $bot->sendMessage($chatId, "<b>My Wallet</b>\n\nCurrent balance: <b>₹".number_format($customer->wallet_balance_paise / 100, 2)."</b>\n\nTo add balance, contact @digixostore and share your customer ID: <code>{$customer->customer_number}</code>.", [
                [['text' => '➕ Add Balance', 'url' => 'https://t.me/digixostore'], ['text' => '⌂ Main Menu', 'callback_data' => 'home']],
            ]);
            return;
        }
        if ($data === 'home') {
            $bot->sendMessage($chatId, $this->mainMenu($customer), $this->menuKeyboard());
            return;
        }
        if ($data === 'categories' || $data === 'shop') {
            $categories = DB::table('categories')->where('is_active', true)->orderBy('display_order')->limit(10)->get();
            if ($categories->isEmpty()) {
                $bot->sendMessage($chatId, 'No categories are available yet. Please check back soon.', [[['text' => '⌂ Main Menu', 'callback_data' => 'home']]]);
                return;
            }
            $buttons = $categories->map(fn ($category) => [['text' => '📂 '.$category->name, 'callback_data' => 'category:'.$category->id]])->all();
            $buttons[] = [['text' => '⌂ Main Menu', 'callback_data' => 'home']];
            $bot->sendMessage($chatId, '<b>Shop by Category</b>\n\nChoose a category:', $buttons);
            return;
        }
        if (preg_match('/^category:(\d+)$/', $data, $matches)) {
            $products = DB::table('products')->where('category_id', $matches[1])->where('is_active', true)->orderBy('display_order')->limit(10)->get();
            $buttons = $products->map(fn ($product) => [['text' => '🛍️ '.$product->name, 'callback_data' => 'product:'.$product->id]])->all();
            $buttons[] = [['text' => '‹ Categories', 'callback_data' => 'categories'], ['text' => '⌂ Main Menu', 'callback_data' => 'home']];
            $bot->sendMessage($chatId, $products->isEmpty() ? 'No products are available in this category.' : '<b>Choose a product</b>', $buttons);
            return;
        }
        if (preg_match('/^product:(\d+)$/', $data, $matches)) {
            $product = DB::table('products')->find($matches[1]);
            $plans = DB::table('plans')->where('product_id', $matches[1])->where('is_active', true)->orderBy('display_order')->get();
            $buttons = $plans->map(fn ($plan) => [['text' => "{$plan->name} • {$plan->validity} • ₹".number_format($plan->price_paise / 100, 2), 'callback_data' => 'plan:'.$plan->id]])->all();
            $buttons[] = [['text' => '‹ Categories', 'callback_data' => 'categories'], ['text' => '⌂ Main Menu', 'callback_data' => 'home']];
            $bot->sendMessage($chatId, '<b>'.TelegramBot::escape($product->name).'</b>\n<blockquote>'.TelegramBot::escape($product->description ?? '').'</blockquote>\nSelect the plan that matches your requirement.', $buttons);
        }
    }
}
