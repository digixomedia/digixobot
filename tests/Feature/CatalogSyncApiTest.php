<?php

namespace Tests\Feature;

use App\Support\DigiXOCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogSyncApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_request_syncs_the_complete_catalog_in_one_transaction(): void
    {
        config(['services.catalog_sync.token' => 'test-catalog-sync-token']);

        $this->withToken('test-catalog-sync-token')
            ->postJson('/api/admin/catalog/sync', DigiXOCatalog::payload())
            ->assertOk()
            ->assertJson([
                'created' => 74,
                'updated' => 0,
                'skipped' => 0,
                'failed' => 0,
            ]);

        $this->assertDatabaseCount('categories', 6);
        $this->assertDatabaseCount('products', 34);
        $this->assertDatabaseCount('plans', 34);
        $this->assertDatabaseHas('plans', [
            'slug' => 'mercury-2-years',
            'validity' => '2 Years',
            'price_paise' => 199900,
        ]);
    }
}
