<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class WalletService
{
    public function credit(Customer $customer, int $amountPaise, string $reference, ?int $adminId): void
    {
        if ($amountPaise <= 0) {
            throw new InvalidArgumentException('Wallet credit must be positive.');
        }

        $balance = DB::transaction(function () use ($customer, $amountPaise, $reference, $adminId) {
            $locked = DB::table('customers')->lockForUpdate()->find($customer->id);
            $balance = $locked->wallet_balance_paise + $amountPaise;
            DB::table('customers')->where('id', $customer->id)->update(['wallet_balance_paise' => $balance, 'updated_at' => now()]);
            DB::table('wallet_transactions')->insert([
                'customer_id' => $customer->id,
                'type' => 'manual_credit',
                'amount_paise' => $amountPaise,
                'balance_after_paise' => $balance,
                'reference' => $reference,
                'idempotency_key' => 'admin-credit:'.Str::uuid(),
                'created_by' => $adminId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('audit_logs')->insert([
                'user_id' => $adminId,
                'action' => 'wallet.credit',
                'auditable_type' => Customer::class,
                'auditable_id' => $customer->id,
                'after' => json_encode(['amount_paise' => $amountPaise, 'balance_after_paise' => $balance, 'reference' => $reference]),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            return $balance;
        }, 3);

        app(TelegramBot::class)->sendMessage($customer->telegram_id,
            '<b>Wallet credited</b>\n\nAmount: <b>₹'.number_format($amountPaise / 100, 2).'</b>\nNew balance: <b>₹'.number_format($balance / 100, 2).'</b>\nReference: '.TelegramBot::escape($reference)
        );
    }
}
