<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $guarded = [];

    protected function casts(): array { return ['paid_at' => 'datetime', 'delivered_at' => 'datetime']; }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function walletTransactions(): HasMany { return $this->hasMany(WalletTransaction::class); }
}
