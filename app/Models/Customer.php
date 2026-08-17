<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $guarded = [];

    protected function casts(): array { return ['last_activity_at' => 'datetime']; }
    public function orders(): HasMany { return $this->hasMany(Order::class); }
    public function walletTransactions(): HasMany { return $this->hasMany(WalletTransaction::class); }
}
