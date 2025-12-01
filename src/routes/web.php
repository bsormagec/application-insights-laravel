<?php
use Illuminate\Support\Facades\Route;
use Sormagec\AppInsightsLaravel\Http\Controllers\AppInsightsController;

// Client-side telemetry collection endpoint
// Rate limited to prevent abuse (60 requests per minute per IP)
Route::post('/appinsights/collect', [AppInsightsController::class, 'collect'])
    ->middleware('throttle:60,1');
