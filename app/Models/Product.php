<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $guarded = [];

    protected function casts(): array { return ['is_active' => 'boolean', 'is_featured' => 'boolean', 'is_deal' => 'boolean']; }
    public function category(): BelongsTo { return $this->belongsTo(Category::class); }
    public function plans(): HasMany { return $this->hasMany(Plan::class); }
}
