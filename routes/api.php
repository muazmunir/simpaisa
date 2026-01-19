<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WalletTransactionController;
use App\Http\Controllers\TransactionInquiryController;

Route::prefix('v2/wallets')->group(function () {
    Route::post('/transaction/initiate', [WalletTransactionController::class, 'initiate']);
    Route::post('/transaction/verify', [WalletTransactionController::class, 'verify']);
    Route::post('/transaction/finalize', [WalletTransactionController::class, 'finalize']);
    Route::post('/transaction/delink', [WalletTransactionController::class, 'delink']);
});

Route::prefix('v2/inquire')->group(function () {
    Route::post('/wallet/transaction/inquire', [TransactionInquiryController::class, 'inquire']);
});

// Webhook/Callback routes from Simpaisa
Route::prefix('webhooks')->group(function () {
    // Wallet transaction webhooks
    Route::post('/wallet/transaction', [\App\Http\Controllers\SimpaisaWebhookController::class, 'handleWalletTransaction'])
        ->middleware(\App\Http\Middleware\VerifySimpaisaSignature::class);
    
    // Disbursement webhooks
    Route::post('/disbursement', [\App\Http\Controllers\SimpaisaWebhookController::class, 'handleDisbursement'])
        ->middleware(\App\Http\Middleware\VerifySimpaisaSignature::class);
    
    // Generic webhook (catch-all)
    Route::post('/', [\App\Http\Controllers\SimpaisaWebhookController::class, 'handleGeneric'])
        ->middleware(\App\Http\Middleware\VerifySimpaisaSignature::class);
});

// Disbursement routes
Route::prefix('disbursements')->group(function () {
    Route::get('/register-customer', [\App\Http\Controllers\DisbursementController::class, 'fetchCustomer']);
    Route::post('/register-customer', [\App\Http\Controllers\DisbursementController::class, 'registerCustomer']);
    Route::put('/register-customer', [\App\Http\Controllers\DisbursementController::class, 'updateCustomer']);
    Route::get('/banks', [\App\Http\Controllers\DisbursementController::class, 'fetchBanks']);
    Route::get('/balance-data', [\App\Http\Controllers\DisbursementController::class, 'fetchBalanceData']);
    Route::get('/reasons', [\App\Http\Controllers\DisbursementController::class, 'fetchReasons']);
    Route::post('/fetch-account', [\App\Http\Controllers\DisbursementController::class, 'fetchAccount']);
    Route::post('/initiate', [\App\Http\Controllers\DisbursementController::class, 'initiateDisbursement']);
    Route::put('/initiate', [\App\Http\Controllers\DisbursementController::class, 'updateDisbursement']);
    Route::post('/', [\App\Http\Controllers\DisbursementController::class, 'listDisbursements'])->middleware(\App\Http\Middleware\VerifySimpaisaSignature::class);
});
