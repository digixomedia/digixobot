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
        $this->adjust($customer, $amountPaise, 'manual_credit', $reference, $adminId);
    }

    public function adjust(Customer $customer, int $amountPaise, string $type, string $reference, ?int $adminId): void
    {
        if ($amountPaise === 0 || ! in_array($type, ['manual_credit', 'promotional_credit', 'admin_correction'], true)) {
            throw new InvalidArgumentException('Invalid wallet adjustment.');
        }
        if ($type !== 'admin_correction' && $amountPaise < 1) {
            throw new InvalidArgumentException('Wallet credit must be positive.');
        }

        $balance = DB::transaction(function () use ($customer, $amountPaise, $type, $reference, $adminId) {
            $locked = DB::table('customers')->lockForUpdate()->find($customer->id);
            $balance = $locked->wallet_balance_paise + $amountPaise;
            if ($balance < 0) { throw new InvalidArgumentException('Adjustment would make the wallet negative.'); }
            DB::table('customers')->where('id', $customer->id)->update(['wallet_balance_paise' => $balance, 'updated_at' => now()]);
            DB::table('wallet_transactions')->insert([
                'customer_id' => $customer->id,
                'type' => $type,
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
                'action' => 'wallet.'.$type,
                'auditable_type' => Customer::class,
                'auditable_id' => $customer->id,
                'after' => json_encode(['amount_paise' => $amountPaise, 'balance_after_paise' => $balance, 'reference' => $reference]),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            return $balance;
        }, 3);

        app(TelegramBot::class)->sendMessage($customer->telegram_id,
            '<b>Wallet updated</b>\n\nAmount: <b>'.($amountPaise >= 0 ? '+' : '−').'₹'.number_format(abs($amountPaise) / 100, 2).'</b>\nNew balance: <b>₹'.number_format($balance / 100, 2).'</b>\nReference: '.TelegramBot::escape($reference)
        );
    }
}
