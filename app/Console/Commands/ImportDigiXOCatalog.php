<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Plan;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ImportDigiXOCatalog extends Command
{
    protected $signature = 'catalog:import
        {--dry-run : Roll back all catalog changes after showing the result}
        {--backup= : Relative path on the local storage disk for the pre-import JSON backup}';

    protected $description = 'Idempotently import the authoritative DigiXO Store catalog';

    public function handle(): int
    {
        $catalog = require database_path('data/digixo_catalog.php');
        $productCount = array_sum(array_map(fn (array $category): int => count($category['products']), $catalog));

        if (count($catalog) !== 6 || $productCount !== 34) {
            throw new RuntimeException('Catalog source must contain exactly 6 categories and 34 products.');
        }

        $dryRun = (bool) $this->option('dry-run');
        $backupPath = null;

        if (! $dryRun) {
            $backupPath = $this->backupCatalog();
            $this->info("Backup: {$backupPath}");
        }

        $stats = [
            'categories' => ['total' => 0, 'created' => 0, 'updated' => 0, 'unchanged' => 0],
            'products' => ['total' => 0, 'created' => 0, 'updated' => 0, 'unchanged' => 0],
            'plans' => ['total' => 0, 'created' => 0, 'updated' => 0, 'unchanged' => 0],
        ];

        DB::beginTransaction();

        try {
            foreach ($catalog as $categoryData) {
                $category = Category::query()->where('slug', $categoryData['slug'])->lockForUpdate()->first()
                    ?? new Category(['slug' => $categoryData['slug']]);

                $this->saveAndCount($category, [
                    'name' => $categoryData['name'],
                    'description' => null,
                    'display_order' => $categoryData['display_order'],
                    'is_active' => true,
                ], $stats['categories']);

                foreach ($categoryData['products'] as $productOrder => $productData) {
                    $product = Product::query()->where('slug', $productData['slug'])->lockForUpdate()->first()
                        ?? new Product(['slug' => $productData['slug']]);

                    $this->saveAndCount($product, [
                        'category_id' => $category->id,
                        'name' => $productData['name'],
                        'description' => $productData['description'],
                        'is_active' => true,
                        'is_featured' => false,
                        'is_deal' => false,
                        'display_order' => $productOrder + 1,
                    ], $stats['products']);

                    $plan = Plan::query()
                        ->where('product_id', $product->id)
                        ->where('name', $productData['plan'])
                        ->lockForUpdate()
                        ->first();

                    if (! $plan) {
                        $existingPlanIds = Plan::query()->where('product_id', $product->id)->lockForUpdate()->pluck('id');
                        $replaceablePlanId = $existingPlanIds->count() === 1 ? $existingPlanIds->first() : null;
                        $plan = $replaceablePlanId && ! DB::table('order_items')->where('plan_id', $replaceablePlanId)->exists()
                            ? Plan::find($replaceablePlanId)
                            : null;
                    }

                    $plan ??= new Plan([
                        'product_id' => $product->id,
                    ]);

                    $this->saveAndCount($plan, [
                        'name' => $productData['plan'],
                        'validity' => $productData['validity'],
                        'price_paise' => $productData['price_rupees'] * 100,
                        'compare_at_price_paise' => null,
                        'stock' => null,
                        'delivery_method' => null,
                        'delivery_estimate' => null,
                        'activation_method' => null,
                        'warranty' => null,
                        'conditions' => null,
                        'is_active' => true,
                        'display_order' => 1,
                    ], $stats['plans']);
                }
            }

            if ($dryRun) {
                DB::rollBack();
            } else {
                DB::commit();
            }
        } catch (Throwable $exception) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            throw $exception;
        }

        $this->table(
            ['Entity', 'Processed', 'Created', 'Updated', 'Unchanged'],
            collect($stats)->map(fn (array $values, string $entity): array => [
                ucfirst($entity),
                $values['total'],
                $values['created'],
                $values['updated'],
                $values['unchanged'],
            ])->values()->all(),
        );
        $this->line('Demo records deactivated: 0');
        $this->info($dryRun ? 'Dry run complete; all database changes were rolled back.' : 'Catalog import complete.');

        return self::SUCCESS;
    }

    private function saveAndCount(Category|Product|Plan $model, array $attributes, array &$stats): void
    {
        $exists = $model->exists;
        $model->fill($attributes);
        $changed = ! $exists || $model->isDirty();

        if ($changed) {
            $model->save();
        }

        $stats['total']++;
        $stats[$exists ? ($changed ? 'updated' : 'unchanged') : 'created']++;
    }

    private function backupCatalog(): string
    {
        $path = $this->option('backup') ?: 'catalog-backups/catalog-'.now()->format('Ymd-His-u').'.json';
        $path = str_replace('\\', '/', (string) $path);

        if (str_starts_with($path, '/') || preg_match('/^[A-Za-z]:\//', $path) || in_array('..', explode('/', $path), true)) {
            throw new RuntimeException('The backup path must be relative to the local storage disk.');
        }

        $payload = json_encode([
            'generated_at' => now()->toIso8601String(),
            'categories' => DB::table('categories')->orderBy('id')->get(),
            'products' => DB::table('products')->orderBy('id')->get(),
            'plans' => DB::table('plans')->orderBy('id')->get(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        if (! Storage::disk('local')->put($path, $payload)) {
            throw new RuntimeException('Catalog backup could not be written.');
        }

        return Storage::disk('local')->path($path);
    }
}
