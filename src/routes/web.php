<?php
use Illuminate\Support\Facades\Route;
use Sormagec\AppInsightsLaravel\Http\Controllers\AppInsightsController;

Route::post('/appinsights/collect', [AppInsightsController::class, 'collect']);
