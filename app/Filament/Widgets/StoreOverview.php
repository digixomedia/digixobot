<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class StoreOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $todayOrders = DB::table('orders')->whereDate('created_at', today())->count();
        $todayRevenue = DB::table('orders')->whereDate('created_at', today())->whereNotIn('status', ['refunded', 'failed', 'cancelled'])->sum('total_paise');
        $walletLiability = DB::table('customers')->sum('wallet_balance_paise');
        $lowStock = DB::table('plans')->where('is_active', true)->where('stock', '<=', 5)->count();

        return [
            Stat::make('Orders today', number_format($todayOrders)),
            Stat::make('Revenue today', '₹'.number_format($todayRevenue / 100, 2)),
            Stat::make('Customers', number_format(DB::table('customers')->count())),
            Stat::make('Wallet liability', '₹'.number_format($walletLiability / 100, 2)),
            Stat::make('Pending fulfilment', number_format(DB::table('orders')->whereIn('status', ['paid', 'processing'])->count())),
            Stat::make('Low stock plans', number_format($lowStock))->color($lowStock > 0 ? 'danger' : 'success'),
        ];
    }
}
