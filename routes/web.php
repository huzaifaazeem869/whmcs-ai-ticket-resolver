<?php

declare(strict_types=1);

use App\Http\Controllers\Api\WhmcsMockController;
use App\Services\HostingIssuesService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $issues = HostingIssuesService::all();
    $byCategory = HostingIssuesService::byCategory();
    return view('welcome', compact('issues', 'byCategory'));
});

Route::post('/test-issue', function (Request $request) {
    // Automatically inject active SaaS test license for local playground
    $request->headers->set('X-AI-License-Key', 'HZ-AI-SUB-2026-LIVE');
    $request->merge(['license_key' => 'HZ-AI-SUB-2026-LIVE']);
    
    // Pass through license middleware check
    $middleware = new \App\Http\Middleware\VerifyWhmcsLicense();
    return $middleware->handle($request, function ($req) {
        $controller = app(WhmcsMockController::class);
        return $controller->analyzeTicket($req);
    });
});
