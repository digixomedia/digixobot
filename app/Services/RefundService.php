<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RefundService
{
    public function refund(Order $order, string $reason, ?int $adminId): void
    {
        $result = DB::transaction(function () use ($order, $reason, $adminId) {
            $lockedOrder = DB::table('orders')->lockForUpdate()->find($order->id);
            if (! $lockedOrder || $lockedOrder->status === 'refunded') {
                throw new RuntimeException('This order has already been refunded.');
            }
            if (! in_array($lockedOrder->status, ['paid', 'processing', 'delivered'], true)) {
                throw new RuntimeException('Only paid orders can be refunded.');
            }

            $customer = DB::table('customers')->lockForUpdate()->find($lockedOrder->customer_id);
            $balance = $customer->wallet_balance_paise + $lockedOrder->total_paise;
            DB::table('customers')->where('id', $customer->id)->update([
                'wallet_balance_paise' => $balance,
                'total_spend_paise' => max(0, $customer->total_spend_paise - $lockedOrder->total_paise),
                'updated_at' => now(),
            ]);
            DB::table('orders')->where('id', $lockedOrder->id)->update(['status' => 'refunded', 'updated_at' => now()]);
            foreach (DB::table('order_items')->where('order_id', $lockedOrder->id)->get() as $item) {
                DB::table('plans')->where('id', $item->plan_id)->increment('stock', $item->quantity);
            }
            DB::table('wallet_transactions')->insert([
                'customer_id' => $customer->id, 'order_id' => $lockedOrder->id, 'type' => 'refund_credit',
                'amount_paise' => $lockedOrder->total_paise, 'balance_after_paise' => $balance,
                'reference' => $lockedOrder->order_number, 'idempotency_key' => 'refund:'.$lockedOrder->id,
                'note' => $reason, 'created_by' => $adminId, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('audit_logs')->insert([
                'user_id' => $adminId, 'action' => 'order.refund', 'auditable_type' => Order::class,
                'auditable_id' => $lockedOrder->id, 'before' => json_encode(['status' => $lockedOrder->status]),
                'after' => json_encode(['status' => 'refunded', 'amount_paise' => $lockedOrder->total_paise, 'reason' => $reason]),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            return [$customer->telegram_id, $lockedOrder->order_number, $lockedOrder->total_paise, $balance];
        }, 3);

        [$telegramId, $number, $amount, $balance] = $result;
        app(TelegramBot::class)->sendMessage($telegramId,
            '<b>Order refunded</b>\n\nOrder: <code>'.TelegramBot::escape($number).'</code>\nRefund: <b>₹'.number_format($amount / 100, 2).'</b>\nWallet balance: <b>₹'.number_format($balance / 100, 2).'</b>'
        );
    }
}
