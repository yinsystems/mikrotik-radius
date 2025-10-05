<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ReddeCallbackController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

/*
|--------------------------------------------------------------------------
| Redde Callback Routes (No authentication required)
|--------------------------------------------------------------------------
*/
Route::prefix('redde/callback')->withoutMiddleware(['auth:sanctum'])->group(function () {
    Route::post('/receive', [ReddeCallbackController::class, 'handleReceiveCallback']);
    Route::post('/cashout', [ReddeCallbackController::class, 'handleCashoutCallback']);
});
