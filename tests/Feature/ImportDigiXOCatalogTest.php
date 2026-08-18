<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportDigiXOCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_reports_the_authoritative_counts_and_rolls_back(): void
    {
        Storage::fake('local');

        $this->assertSame(0, Artisan::call('catalog:import', ['--dry-run' => true]));

        $output = Artisan::output();
        $this->assertStringContainsString('Categories', $output);
        $this->assertStringContainsString('Products', $output);
        $this->assertStringContainsString('Plans', $output);
        $this->assertStringContainsString('Dry run complete', $output);
        $this->assertDatabaseCount('categories', 0);
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('plans', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_import_is_idempotent_preserves_unrelated_data_and_creates_a_backup(): void
    {
        Storage::fake('local');
        $now = now();
        $unrelatedCategoryId = DB::table('categories')->insertGetId([
            'name' => 'Existing Catalog', 'slug' => 'existing-catalog', 'display_order' => 99,
            'is_active' => false, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $unrelatedProductId = DB::table('products')->insertGetId([
            'category_id' => $unrelatedCategoryId, 'name' => 'Existing Product', 'slug' => 'existing-product',
            'description' => 'Must remain untouched.', 'is_active' => false, 'display_order' => 1,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('plans')->insert([
            'product_id' => $unrelatedProductId, 'name' => 'Existing Plan', 'validity' => '30 days',
            'price_paise' => 12300, 'stock' => 4, 'is_active' => false, 'display_order' => 1,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        $this->assertSame(0, Artisan::call('catalog:import'));

        $catalog = require database_path('data/digixo_catalog.php');
        $categorySlugs = array_column($catalog, 'slug');
        $productSlugs = collect($catalog)->flatMap(fn (array $category) => array_column($category['products'], 'slug'))->all();

        $this->assertSame(6, DB::table('categories')->whereIn('slug', $categorySlugs)->count());
        $this->assertSame(34, DB::table('products')->whereIn('slug', $productSlugs)->count());
        $this->assertSame(34, DB::table('plans')->whereIn('product_id', DB::table('products')->whereIn('slug', $productSlugs)->select('id'))->count());
        $this->assertDatabaseHas('products', ['slug' => 'existing-product', 'description' => 'Must remain untouched.', 'is_active' => false]);
        $this->assertDatabaseHas('plans', ['product_id' => $unrelatedProductId, 'price_paise' => 12300, 'stock' => 4, 'is_active' => false]);

        $mercury = DB::table('products')->where('slug', 'mercury-two-years')->first();
        $this->assertDatabaseHas('plans', [
            'product_id' => $mercury->id,
            'name' => 'Mercury — 2 Years',
            'validity' => '2 Years',
            'price_paise' => 199900,
            'stock' => null,
            'is_active' => true,
        ]);
        $this->assertSame(33, DB::table('plans')->whereIn('product_id', DB::table('products')->whereIn('slug', $productSlugs)->select('id'))->where('validity', '1 Year')->count());
        $this->assertSame(1, DB::table('plans')->whereIn('product_id', DB::table('products')->whereIn('slug', $productSlugs)->select('id'))->where('validity', '2 Years')->count());
        $this->assertCount(1, Storage::disk('local')->allFiles('catalog-backups'));

        $idsBefore = DB::table('products')->whereIn('slug', $productSlugs)->orderBy('slug')->pluck('id', 'slug')->all();
        $this->assertSame(0, Artisan::call('catalog:import'));
        $this->assertSame($idsBefore, DB::table('products')->whereIn('slug', $productSlugs)->orderBy('slug')->pluck('id', 'slug')->all());
        $this->assertSame(6, DB::table('categories')->whereIn('slug', $categorySlugs)->count());
        $this->assertSame(34, DB::table('products')->whereIn('slug', $productSlugs)->count());
        $this->assertSame(34, DB::table('plans')->whereIn('product_id', DB::table('products')->whereIn('slug', $productSlugs)->select('id'))->count());
        $this->assertStringContainsString('Unchanged', Artisan::output());
        $this->assertCount(2, Storage::disk('local')->allFiles('catalog-backups'));
    }
}
