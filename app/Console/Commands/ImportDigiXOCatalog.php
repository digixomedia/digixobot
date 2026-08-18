<?php

namespace App\Console\Commands;

use App\Services\CatalogSyncService;
use App\Support\DigiXOCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ImportDigiXOCatalog extends Command
{
    protected $signature = 'catalog:import
        {--dry-run : Roll back all catalog changes after showing the result}
        {--backup= : Relative path on the local storage disk for the pre-import JSON backup}';

    protected $description = 'Idempotently import the authoritative DigiXO Store catalog';

    public function handle(): int
    {
        $categories = DigiXOCatalog::payload()['categories'];
        $productCount = array_sum(array_map(fn (array $category): int => count($category['products']), $categories));

        if (count($categories) !== 6 || $productCount !== 34) {
            throw new RuntimeException('Catalog source must contain exactly 6 categories and 34 products.');
        }

        $dryRun = (bool) $this->option('dry-run');
        $backupPath = null;

        if (! $dryRun) {
            $backupPath = $this->backupCatalog();
            $this->info("Backup: {$backupPath}");
        }

        $result = app(CatalogSyncService::class)->sync($categories, $dryRun);

        $this->table(
            ['Entity', 'Processed', 'Created', 'Updated', 'Unchanged'],
            collect($result['entities'])->map(fn (array $values, string $entity): array => [
                ucfirst($entity),
                array_sum($values),
                $values['created'],
                $values['updated'],
                $values['skipped'],
            ])->values()->all(),
        );
        $this->line('Demo records deactivated: 0');
        $this->info($dryRun ? 'Dry run complete; all database changes were rolled back.' : 'Catalog import complete.');

        return self::SUCCESS;
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
