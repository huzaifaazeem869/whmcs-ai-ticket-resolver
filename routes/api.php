<?php

declare(strict_types=1);

use App\Http\Controllers\Api\WhmcsMockController;
use Illuminate\Support\Facades\Route;

Route::get('/clients', [WhmcsMockController::class, 'getClients']);
Route::get('/invoices', [WhmcsMockController::class, 'getInvoices']);
Route::get('/tickets', [WhmcsMockController::class, 'getTickets']);
Route::get('/tickets/{id}', [WhmcsMockController::class, 'getTicket']);

Route::match(['get', 'post'], '/tickets/{id}/analyze', [WhmcsMockController::class, 'analyzeTicket']);