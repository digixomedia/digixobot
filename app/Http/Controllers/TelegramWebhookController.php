<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Services\TelegramBot;
use App\Services\SettingService;

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
            if (DB::table('telegram_updates')->where('update_id', $updateId)->whereNotNull('processed_at')->exists()) {
                return response()->noContent();
            }
            return response('Update is already being processed.', 409);
        }

        try {
            $message = $request->input('message');
            if (is_array($message) && isset($message['chat']['id'])) {
                $this->handleMessage($message);
            }
            if (is_array($request->input('callback_query'))) {
                $this->handleCallback($request->input('callback_query'));
            }
            DB::table('telegram_updates')->where('update_id', $updateId)->update(['processed_at' => now()]);
        } catch (\Throwable $exception) {
            DB::table('telegram_updates')->where('update_id', $updateId)->delete();
            report($exception);
            return response('Temporary processing failure.', 500);
        }
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
            return;
        }

        $bot = app(TelegramBot::class);
        if ($text === '/orders') {
            $bot->sendMessage($chatId, $this->ordersText($customer), [[['text' => '⌂ Main Menu', 'callback_data' => 'home']]]);
        } elseif ($text === '/wallet') {
            $bot->sendMessage($chatId, $this->walletText($customer), $this->walletKeyboard());
        } elseif ($text === '/account') {
            $bot->sendMessage($chatId, $this->accountText($customer), [[['text' => '⌂ Main Menu', 'callback_data' => 'home']]]);
        } elseif ($text === '/support') {
            $bot->sendMessage($chatId, $this->supportText($customer), [[['text' => '💬 Contact Support', 'url' => 'https://t.me/'.$this->supportUsername()], ['text' => '⌂ Main Menu', 'callback_data' => 'home']]]);
        } elseif ($text === '/terms') {
            $terms = app(SettingService::class)->get('store_terms', 'Digital products are fulfilled according to the plan conditions shown before purchase. Contact support before buying if you need clarification. Eligible refunds are credited back to the DigiXO wallet.');
            $bot->sendMessage($chatId, '<b>Terms</b>\n\n'.TelegramBot::escape($terms), [[['text' => '⌂ Main Menu', 'callback_data' => 'home']]]);
        } elseif (str_starts_with($text, '/search ')) {
            $query = trim(substr($text, 8));
            $products = DB::table('products')->where('is_active', true)->where('name', 'like', '%'.$query.'%')->orderBy('display_order')->limit(10)->get();
            $buttons = $products->map(fn ($product) => [['text' => '🛍️ '.$product->name, 'callback_data' => 'product:'.$product->id]])->all();
            $buttons[] = [['text' => '⌂ Main Menu', 'callback_data' => 'home']];
            $bot->sendMessage($chatId, $products->isEmpty() ? 'No matching products found.' : '<b>Search results</b>', $buttons);
        } elseif ($text !== '') {
            $bot->sendMessage($chatId, 'I did not recognize that command. Use the buttons below or send /start.', $this->menuKeyboard());
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
            $bot->sendMessage($chatId, $this->walletText($customer), $this->walletKeyboard());
            return;
        }
        if ($data === 'transactions' || $data === 'refunds') {
            $types = $data === 'refunds' ? ['refund_credit'] : ['manual_credit', 'purchase_debit', 'refund_credit'];
            $transactions = DB::table('wallet_transactions')->where('customer_id', $customer->id)->whereIn('type', $types)->latest()->limit(10)->get();
            $title = $data === 'refunds' ? 'Refund History' : 'Wallet Transactions';
            $text = '<b>'.$title.'</b>\n\n'.($transactions->isEmpty() ? 'No transactions found.' : $transactions->map(function ($transaction) {
                $sign = $transaction->amount_paise >= 0 ? '+' : '−';
                return $sign.'₹'.number_format(abs($transaction->amount_paise) / 100, 2).' · '.TelegramBot::escape(str_replace('_', ' ', ucfirst($transaction->type))).' · '.$transaction->created_at;
            })->implode("\n"));
            $bot->sendMessage($chatId, $text, [[['text' => '‹ Wallet', 'callback_data' => 'wallet'], ['text' => '⌂ Main Menu', 'callback_data' => 'home']]]);
            return;
        }
        if ($data === 'orders') {
            $bot->sendMessage($chatId, $this->ordersText($customer), [[['text' => '⌂ Main Menu', 'callback_data' => 'home']]]);
            return;
        }
        if ($data === 'account') {
            $bot->sendMessage($chatId, $this->accountText($customer), [[['text' => '⌂ Main Menu', 'callback_data' => 'home']]]);
            return;
        }
        if ($data === 'support') {
            DB::table('support_requests')->insert(['customer_id' => $customer->id, 'message' => 'Customer requested Telegram support.', 'status' => 'open', 'created_at' => now(), 'updated_at' => now()]);
            $bot->notifyAdmin('<b>New support request</b>\n\nCustomer: <code>'.TelegramBot::escape($customer->customer_number).'</code>\nOpen the admin panel to respond.');
            $bot->sendMessage($chatId, '<b>Support request recorded</b>\n\nYou can also contact @'.$this->supportUsername().' and share customer ID <code>'.$customer->customer_number.'</code>.', [[['text' => '💬 Contact Support', 'url' => 'https://t.me/'.$this->supportUsername()], ['text' => '⌂ Main Menu', 'callback_data' => 'home']]]);
            return;
        }
        if ($data === 'search') {
            $bot->sendMessage($chatId, '<b>Search Products</b>\n\nSend <code>/search product name</code>.', [[['text' => '⌂ Main Menu', 'callback_data' => 'home']]]);
            return;
        }
        if ($data === 'deals') {
            $deals = DB::table('deals')->join('plans', 'plans.id', '=', 'deals.plan_id')->join('products', 'products.id', '=', 'plans.product_id')->where('deals.is_active', true)->where('plans.is_active', true)->where('products.is_active', true)->where('plans.stock', '>', 0)->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))->select('deals.*', 'plans.name as plan_name', 'products.name as product_name')->limit(10)->get();
            $text = $deals->isEmpty() ? 'No active deals today.' : '<b>Today’s Deals</b>\n\n'.$deals->map(fn ($deal) => '• '.TelegramBot::escape($deal->title).' — ₹'.number_format($deal->deal_price_paise / 100, 2))->implode("\n");
            $buttons = $deals->map(fn ($deal) => [['text' => '🎯 '.$deal->product_name.' · ₹'.number_format($deal->deal_price_paise / 100, 2), 'callback_data' => 'plan:'.$deal->plan_id]])->all();
            $buttons[] = [['text' => '⌂ Main Menu', 'callback_data' => 'home']];
            $bot->sendMessage($chatId, $text, $buttons);
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
            return;
        }
        if (preg_match('/^plan:(\d+)$/', $data, $matches)) {
            $plan = DB::table('plans')->join('products', 'products.id', '=', 'plans.product_id')->select('plans.*', 'products.name as product_name')->where('plans.id', $matches[1])->first();
            if (! $plan || ! $plan->is_active) {
                $bot->sendMessage($chatId, 'This plan is no longer available.', [[['text' => '⌂ Main Menu', 'callback_data' => 'home']]]);
                return;
            }
            $text = '<b>'.TelegramBot::escape($plan->product_name).' — '.TelegramBot::escape($plan->name).'</b>'
                ."\n<blockquote>".TelegramBot::escape($plan->validity)." plan</blockquote>"
                ."\n<b>Validity:</b> ".TelegramBot::escape($plan->validity)
                ."\n<b>Delivery:</b> ".TelegramBot::escape($plan->delivery_estimate ?: 'Contact support')
                ."\n<b>Activation:</b> ".TelegramBot::escape($plan->activation_method ?: 'Provided after purchase')
                ."\n<b>Warranty:</b> ".TelegramBot::escape($plan->warranty ?: 'As stated by seller')
                ."\n<b>Price:</b> ₹".number_format($this->effectivePrice((int) $plan->id, (int) $plan->price_paise) / 100, 2)
                ."\n<b>Stock:</b> ".($plan->stock > 0 ? $plan->stock.' available' : 'Sold out');
            $bot->sendMessage($chatId, $text, [[['text' => '⚡ Buy Now', 'callback_data' => 'buy:'.$plan->id]], [['text' => '‹ Other Plans', 'callback_data' => 'product:'.$plan->product_id], ['text' => '⌂ Main Menu', 'callback_data' => 'home']]]);
            return;
        }
        if (preg_match('/^buy:(\d+)$/', $data, $matches)) {
            $plan = DB::table('plans')->find($matches[1]);
            if (! $plan) { return; }
            $key = str()->random(16);
            $price = $this->effectivePrice((int) $plan->id, (int) $plan->price_paise);
            $bot->sendMessage($chatId, '<b>Confirm Purchase</b>\n\n'.TelegramBot::escape($plan->name)."\nTotal: <b>₹".number_format($price / 100, 2)."</b>\n\nThis amount will be deducted from your wallet.", [[['text' => '✅ Confirm & Pay', 'callback_data' => 'confirm:'.$plan->id.':'.$key]], [['text' => '✕ Cancel', 'callback_data' => 'plan:'.$plan->id]]]);
            return;
        }
        if (preg_match('/^confirm:(\d+):([A-Za-z0-9]+)$/', $data, $matches)) {
            try {
                $orderId = app(\App\Services\PurchaseService::class)->purchase($customer->id, (int) $matches[1], $matches[2]);
                $order = DB::table('orders')->find($orderId);
                $bot->sendMessage($chatId, '<b>Payment successful</b>\n\nOrder <code>'.TelegramBot::escape($order->order_number).'</code> is now in the fulfilment queue.', [[['text' => '📦 My Orders', 'callback_data' => 'orders'], ['text' => '⌂ Main Menu', 'callback_data' => 'home']]]);
                $bot->notifyAdmin('<b>New paid order</b>\n\nOrder: <code>'.TelegramBot::escape($order->order_number).'</code>\nCustomer: <code>'.TelegramBot::escape($customer->customer_number).'</code>\nTotal: <b>₹'.number_format($order->total_paise / 100, 2).'</b>');
            } catch (\RuntimeException $exception) {
                $bot->sendMessage($chatId, TelegramBot::escape($exception->getMessage()), [[['text' => '💰 Add Balance', 'callback_data' => 'wallet'], ['text' => '⌂ Main Menu', 'callback_data' => 'home']]]);
            }
        }
    }

    private function walletText(object $customer): string
    {
        return "<b>My Wallet</b>\n\nCurrent balance: <b>₹".number_format($customer->wallet_balance_paise / 100, 2)."</b>\n\nTo add balance, contact @{$this->supportUsername()} and share your customer ID: <code>{$customer->customer_number}</code>.";
    }

    private function accountText(object $customer): string
    {
        return '<b>My Account</b>\n\nName: '.TelegramBot::escape($customer->name ?: 'Customer').'\nCustomer ID: <code>'.$customer->customer_number.'</code>\nRegistered: '.$customer->created_at;
    }

    private function ordersText(object $customer): string
    {
        $orders = DB::table('orders')->where('customer_id', $customer->id)->latest()->limit(10)->get();
        if ($orders->isEmpty()) { return '<b>My Orders</b>\n\nYou have no orders yet.'; }
        return '<b>My Orders</b>\n\n'.$orders->map(fn ($order) => '<code>'.TelegramBot::escape($order->order_number).'</code> — '.TelegramBot::escape(ucfirst($order->status)).' — ₹'.number_format($order->total_paise / 100, 2))->implode("\n");
    }

    private function walletKeyboard(): array
    {
        return [
            [['text' => '➕ Add Balance', 'url' => 'https://t.me/'.$this->supportUsername()]],
            [['text' => '📒 Transactions', 'callback_data' => 'transactions'], ['text' => '↩ Refunds', 'callback_data' => 'refunds']],
            [['text' => '⌂ Main Menu', 'callback_data' => 'home']],
        ];
    }

    private function supportUsername(): string
    {
        return ltrim(app(SettingService::class)->get('support_username', 'digixostore'), '@');
    }

    private function supportText(object $customer): string
    {
        return '<b>Help & Support</b>\n\nContact @'.$this->supportUsername().' and include your customer ID: <code>'.$customer->customer_number.'</code>.';
    }

    private function effectivePrice(int $planId, int $regularPrice): int
    {
        $dealPrice = DB::table('deals')->where('plan_id', $planId)->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->min('deal_price_paise');
        return $dealPrice === null ? $regularPrice : min((int) $dealPrice, $regularPrice);
    }
}
