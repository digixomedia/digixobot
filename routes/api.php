<?php

use App\Http\Controllers\CatalogSyncController;
use App\Http\Middleware\EnsureCatalogSyncToken;
use Illuminate\Support\Facades\Route;

Route::post('/admin/catalog/sync', CatalogSyncController::class)
    ->middleware(EnsureCatalogSyncToken::class)
    ->name('api.admin.catalog.sync');
