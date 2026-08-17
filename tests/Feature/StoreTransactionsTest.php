<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Services\PurchaseService;
use App\Services\RefundService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class StoreTransactionsTest extends TestCase
{
    use RefreshDatabase;

    private function seedStore(int $balance = 10000, int $stock = 2): array
    {
        $now = now();
        $customerId = DB::table('customers')->insertGetId([
            'telegram_id' => 123456, 'customer_number' => 'DXO-TEST', 'wallet_balance_paise' => $balance,
            'total_spend_paise' => 0, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $categoryId = DB::table('categories')->insertGetId(['name' => 'Test', 'slug' => 'test', 'created_at' => $now, 'updated_at' => $now]);
        $productId = DB::table('products')->insertGetId(['category_id' => $categoryId, 'name' => 'Product', 'slug' => 'product', 'created_at' => $now, 'updated_at' => $now]);
        $planId = DB::table('plans')->insertGetId([
            'product_id' => $productId, 'name' => 'Monthly', 'validity' => '30 days', 'price_paise' => 2500,
            'stock' => $stock, 'created_at' => $now, 'updated_at' => $now,
        ]);
        return [$customerId, $planId];
    }

    public function test_purchase_is_atomic_and_cannot_be_replayed(): void
    {
        [$customerId, $planId] = $this->seedStore();
        $orderId = app(PurchaseService::class)->purchase($customerId, $planId, 'purchase-one');

        $this->assertDatabaseHas('orders', ['id' => $orderId, 'total_paise' => 2500]);
        $this->assertDatabaseHas('customers', ['id' => $customerId, 'wallet_balance_paise' => 7500, 'total_spend_paise' => 2500]);
        $this->assertDatabaseHas('plans', ['id' => $planId, 'stock' => 1]);
        $this->assertDatabaseHas('wallet_transactions', ['order_id' => $orderId, 'amount_paise' => -2500]);

        $this->expectException(RuntimeException::class);
        app(PurchaseService::class)->purchase($customerId, $planId, 'purchase-one');
    }

    public function test_purchase_never_allows_negative_balance_or_negative_stock(): void
    {
        [$customerId, $planId] = $this->seedStore(balance: 1000, stock: 1);
        try { app(PurchaseService::class)->purchase($customerId, $planId, 'insufficient'); } catch (RuntimeException) {}
        $this->assertDatabaseHas('customers', ['id' => $customerId, 'wallet_balance_paise' => 1000]);
        $this->assertDatabaseHas('plans', ['id' => $planId, 'stock' => 1]);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_refund_restores_wallet_spend_and_stock_exactly_once(): void
    {
        Http::fake();
        [$customerId, $planId] = $this->seedStore();
        $order = Order::find(app(PurchaseService::class)->purchase($customerId, $planId, 'refundable'));
        app(RefundService::class)->refund($order, 'Approved test refund', null);

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'refunded']);
        $this->assertDatabaseHas('customers', ['id' => $customerId, 'wallet_balance_paise' => 10000, 'total_spend_paise' => 0]);
        $this->assertDatabaseHas('plans', ['id' => $planId, 'stock' => 2]);
        $this->assertDatabaseHas('wallet_transactions', ['order_id' => $order->id, 'type' => 'refund_credit', 'amount_paise' => 2500]);

        $this->expectException(RuntimeException::class);
        app(RefundService::class)->refund($order, 'Duplicate attempt', null);
    }

    public function test_active_deal_price_is_used_for_purchase(): void
    {
        [$customerId, $planId] = $this->seedStore();
        DB::table('deals')->insert(['plan_id' => $planId, 'title' => 'Test deal', 'deal_price_paise' => 1800, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $orderId = app(PurchaseService::class)->purchase($customerId, $planId, 'deal-purchase');
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'total_paise' => 1800]);
        $this->assertDatabaseHas('customers', ['id' => $customerId, 'wallet_balance_paise' => 8200]);
    }

    public function test_wallet_adjustments_are_ledgered_and_never_make_balance_negative(): void
    {
        Http::fake();
        [$customerId] = $this->seedStore();
        $customer = Customer::find($customerId);
        app(WalletService::class)->adjust($customer, 500, 'promotional_credit', 'Welcome bonus', null);
        $this->assertDatabaseHas('wallet_transactions', ['customer_id' => $customerId, 'type' => 'promotional_credit', 'amount_paise' => 500]);
        $this->assertDatabaseHas('customers', ['id' => $customerId, 'wallet_balance_paise' => 10500]);

        $this->expectException(\InvalidArgumentException::class);
        app(WalletService::class)->adjust($customer, -20000, 'admin_correction', 'Invalid correction', null);
    }
}
