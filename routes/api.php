<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CountryController;
use App\Http\Controllers\Api\V1\SubsidiaryController;

use App\Http\Controllers\Api\V1\RegionController;
use App\Http\Controllers\Api\V1\TerritoryController;
use App\Http\Controllers\Api\V1\TeamController;

Route::get('/login', fn() => response()->json(['message' => 'Non authentifié'], 401))->name('login');

Route::prefix('v1')->group(function () {
    // Auth Routes
    Route::post('/login', [AuthController::class, 'login']);

    // Protected Routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        
        // Organizational Structure
        Route::apiResource('countries', CountryController::class);
        Route::apiResource('subsidiaries', SubsidiaryController::class);
        Route::apiResource('regions', RegionController::class);
        Route::apiResource('territories', TerritoryController::class);
        Route::apiResource('teams', TeamController::class);
    });
});
