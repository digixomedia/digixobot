<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Plan;
use App\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Throwable;

class CatalogSyncService
{
    public function sync(array $categories, bool $dryRun = false): array
    {
        $entities = [
            'categories' => ['created' => 0, 'updated' => 0, 'skipped' => 0],
            'products' => ['created' => 0, 'updated' => 0, 'skipped' => 0],
            'plans' => ['created' => 0, 'updated' => 0, 'skipped' => 0],
        ];

        DB::beginTransaction();

        try {
            foreach ($categories as $categoryData) {
                $category = Category::query()->where('slug', $categoryData['slug'])->lockForUpdate()->first()
                    ?? new Category(['slug' => $categoryData['slug']]);

                $this->saveAndCount($category, $this->onlyPresent($categoryData, [
                    'name', 'description', 'display_order', 'is_active',
                ]), $entities['categories']);

                foreach ($categoryData['products'] as $productData) {
                    $product = Product::query()->where('slug', $productData['slug'])->lockForUpdate()->first()
                        ?? new Product(['slug' => $productData['slug']]);

                    $attributes = $this->onlyPresent($productData, [
                        'name', 'description', 'display_order', 'is_active', 'is_featured', 'is_deal',
                    ]);
                    $attributes['category_id'] = $category->id;
                    $this->saveAndCount($product, $attributes, $entities['products']);

                    foreach ($productData['plans'] as $planData) {
                        $plan = null;

                        if (! empty($planData['slug'])) {
                            $plan = Plan::query()
                                ->where('product_id', $product->id)
                                ->where('slug', $planData['slug'])
                                ->lockForUpdate()
                                ->first();
                        }

                        $plan ??= Plan::query()
                            ->where('product_id', $product->id)
                            ->where('name', $planData['name'])
                            ->lockForUpdate()
                            ->first();
                        $plan ??= new Plan(['product_id' => $product->id]);

                        $this->saveAndCount($plan, $this->onlyPresent($planData, [
                            'slug', 'name', 'validity', 'price_paise', 'compare_at_price_paise', 'stock',
                            'delivery_method', 'delivery_estimate', 'activation_method', 'warranty',
                            'conditions', 'is_active', 'display_order',
                        ]), $entities['plans']);
                    }
                }
            }

            $dryRun ? DB::rollBack() : DB::commit();
        } catch (Throwable $exception) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            throw $exception;
        }

        return [
            'created' => array_sum(array_column($entities, 'created')),
            'updated' => array_sum(array_column($entities, 'updated')),
            'skipped' => array_sum(array_column($entities, 'skipped')),
            'failed' => 0,
            'entities' => $entities,
        ];
    }

    private function onlyPresent(array $data, array $keys): array
    {
        return array_intersect_key($data, array_flip($keys));
    }

    private function saveAndCount(Model $model, array $attributes, array &$stats): void
    {
        $exists = $model->exists;
        $model->fill($attributes);
        $changed = ! $exists || $model->isDirty();

        if ($changed) {
            $model->save();
        }

        $stats[$exists ? ($changed ? 'updated' : 'skipped') : 'created']++;
    }
}
