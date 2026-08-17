<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $now = now();
        DB::table('settings')->insertOrIgnore([
            ['key' => 'support_username', 'value' => 'digixostore', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'low_stock_threshold', 'value' => '5', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'store_terms', 'value' => 'Digital products are fulfilled according to the plan conditions shown before purchase. Contact support before buying if you need clarification. Eligible refunds are credited back to the DigiXO wallet.', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', ['support_username', 'low_stock_threshold', 'store_terms'])->delete();
    }
};
