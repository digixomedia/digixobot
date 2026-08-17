<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class PurchaseService
{
    /** Atomically creates a paid order, debits the ledger, and reserves one plan unit. */
    public function purchase(int $customerId, int $planId, string $purchaseKey): int
    {
        return DB::transaction(function () use ($customerId, $planId, $purchaseKey) {
            if (DB::table('orders')->where('purchase_key', $purchaseKey)->exists()) {
                throw new RuntimeException('This purchase was already processed.');
            }

            $customer = DB::table('customers')->lockForUpdate()->find($customerId);
            $plan = DB::table('plans')->lockForUpdate()->find($planId);
            $product = $plan ? DB::table('products')->find($plan->product_id) : null;
            if (! $customer || ! $plan || ! $product || ! $plan->is_active || ! $product->is_active || $plan->stock < 1) {
                throw new RuntimeException('This plan is no longer available.');
            }
            $dealPrice = DB::table('deals')->where('plan_id', $planId)->where('is_active', true)
                ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
                ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
                ->min('deal_price_paise');
            $price = $dealPrice !== null ? min((int) $dealPrice, (int) $plan->price_paise) : (int) $plan->price_paise;
            if ($customer->wallet_balance_paise < $price) {
                throw new RuntimeException('Insufficient wallet balance.');
            }

            $newBalance = $customer->wallet_balance_paise - $price;
            $orderId = DB::table('orders')->insertGetId([
                'customer_id' => $customerId,
                'order_number' => 'DXO-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
                'status' => 'paid', 'total_paise' => $price,
                'purchase_key' => $purchaseKey, 'paid_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('order_items')->insert([
                'order_id' => $orderId, 'plan_id' => $planId, 'product_name' => $product->name,
                'plan_name' => $plan->name, 'unit_price_paise' => $price,
                'quantity' => 1, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('customers')->where('id', $customerId)->update([
                'wallet_balance_paise' => $newBalance,
                'total_spend_paise' => $customer->total_spend_paise + $price,
                'updated_at' => now(),
            ]);
            DB::table('plans')->where('id', $planId)->decrement('stock');
            DB::table('wallet_transactions')->insert([
                'customer_id' => $customerId, 'order_id' => $orderId, 'type' => 'purchase_debit',
                'amount_paise' => -$price, 'balance_after_paise' => $newBalance,
                'idempotency_key' => 'purchase:'.$purchaseKey, 'created_at' => now(), 'updated_at' => now(),
            ]);
            return $orderId;
        }, 3);
    }
}
