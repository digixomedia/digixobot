<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SettingService
{
    public function get(string $key, ?string $default = null): ?string
    {
        return Cache::remember('setting:'.$key, 300, fn () => DB::table('settings')->where('key', $key)->value('value')) ?? $default;
    }

    public function forget(string $key): void
    {
        Cache::forget('setting:'.$key);
    }
}
