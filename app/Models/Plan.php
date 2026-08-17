<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Plan extends Model
{
    protected $guarded = [];

    protected function casts(): array { return ['is_active' => 'boolean']; }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}
