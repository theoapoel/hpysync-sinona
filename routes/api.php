<?php

use App\Http\Controllers\Api\KitchenApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — aplikasi tablet (Kitchen Monitor)
|--------------------------------------------------------------------------
| Autentikasi via header X-API-KEY / X-API-SECRET, diatur di menu
| Pengaturan (mobile_api_key / mobile_api_secret).
*/

Route::prefix('v1')->middleware('mobile.api')->group(function () {
    Route::get('/kitchen', [KitchenApiController::class, 'index']);
    Route::get('/kitchen/poll', [KitchenApiController::class, 'poll']);
    Route::post('/kitchen/orders/{order}/status', [KitchenApiController::class, 'updateOrderStatus']);
    Route::post('/kitchen/stock-requests/{stockRequest}/status', [KitchenApiController::class, 'updateStockRequestStatus']);
});
