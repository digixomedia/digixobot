<?php

namespace App\Filament\Resources\WalletTransactions\Pages;

use App\Filament\Resources\WalletTransactions\WalletTransactionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateWalletTransaction extends CreateRecord
{
    protected static string $resource = WalletTransactionResource::class;
}
