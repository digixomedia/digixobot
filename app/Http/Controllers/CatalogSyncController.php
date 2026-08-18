<?php

namespace App\Http\Controllers;

use App\Http\Requests\SyncCatalogRequest;
use App\Services\CatalogSyncService;
use Illuminate\Http\JsonResponse;
use Throwable;

class CatalogSyncController extends Controller
{
    public function __invoke(SyncCatalogRequest $request, CatalogSyncService $catalog): JsonResponse
    {
        try {
            return response()->json($catalog->sync($request->validated('categories')));
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Catalog sync failed; no changes were saved.',
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'failed' => 1,
            ], 500);
        }
    }
}
