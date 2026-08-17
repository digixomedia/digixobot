<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Deal;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Setting;
use App\Services\SettingService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach ([Category::class, Product::class, Plan::class, Deal::class, Setting::class] as $modelClass) {
            $modelClass::updated(function (Model $model): void {
                $changes = $model->getChanges();
                unset($changes['updated_at']);
                if ($changes === []) { return; }
                $before = [];
                foreach (array_keys($changes) as $attribute) { $before[$attribute] = $model->getOriginal($attribute); }
                DB::table('audit_logs')->insert([
                    'user_id' => auth()->id(), 'action' => 'record.updated', 'auditable_type' => $model::class,
                    'auditable_id' => $model->getKey(), 'before' => json_encode($before), 'after' => json_encode($changes),
                    'ip_address' => request()?->ip(), 'created_at' => now(), 'updated_at' => now(),
                ]);
                if ($model instanceof Setting) { app(SettingService::class)->forget($model->key); }
            });
        }
    }
}
