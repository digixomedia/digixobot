<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TelegramWebhookController;

Route::post('/telegram/webhook', TelegramWebhookController::class)->name('telegram.webhook');

Route::get('/', function () {
    return redirect('/admin');
});

Route::get('/health', fn () => response()->json(['status' => 'ok']));
