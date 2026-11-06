<?php

declare(strict_types=1);

use App\Http\Controllers\Api\WhmcsMockController;
use Illuminate\Support\Facades\Route;

// Public Mock Testbed endpoints
Route::get('/clients', [WhmcsMockController::class, 'getClients']);
Route::get('/invoices', [WhmcsMockController::class, 'getInvoices']);
Route::get('/tickets', [WhmcsMockController::class, 'getTickets']);
Route::get('/tickets/{id}', [WhmcsMockController::class, 'getTicket']);

// SaaS Protected Endpoints (Requires Active License Key)
Route::middleware('whmcs.license')->group(function (): void {
    Route::get('/license/status', [WhmcsMockController::class, 'licenseStatus']);
    Route::post('/analyze', [WhmcsMockController::class, 'analyzeTicket']);
    Route::match(['get', 'post'], '/tickets/{id}/analyze', [WhmcsMockController::class, 'analyzeTicket']);
});
