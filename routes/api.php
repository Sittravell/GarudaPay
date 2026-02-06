<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AccountController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\FundController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\UserCurrencyController;
use App\Http\Controllers\FavoriteController;

Route::middleware(['auth:api', 'api_key'])->group(function () {
    Route::get('/balance', [AccountController::class, 'totalBalance']);
    Route::post('/account', [AccountController::class, 'store']);
    Route::get('/accounts', [AccountController::class, 'index']);
    Route::get('/account/{id}', [AccountController::class, 'show']);
    Route::post('/account/main', [AccountController::class, 'setMain']);

    Route::post('/funds/add', [FundController::class, 'add']);
    Route::post('/transfer', [TransferController::class, 'transfer']);

    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::post('/transactions/{id}/repeat', [TransactionController::class, 'repeat']);
    Route::get('/currencies', [CurrencyController::class, 'index']);

    Route::get('/user/currency', [UserCurrencyController::class, 'show']);
    Route::post('/user/currency', [UserCurrencyController::class, 'update']);

    Route::get('/favorites', [FavoriteController::class, 'index']);
    Route::post('/favorites', [FavoriteController::class, 'store']);
    Route::post('/favorites/remove', [FavoriteController::class, 'destroy']); // Using POST for remove with body, or DELETE? 
    // DELETE with body is discouraged. Let's strictly use DELETE /favorites/{phone}? Or POST /remove?
    // "add and remove".
    // I will use `POST /favorites/remove` to keep it simple with JSON body { phone_number }.


    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});
