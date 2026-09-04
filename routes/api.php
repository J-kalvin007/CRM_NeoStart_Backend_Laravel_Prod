<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CountryController;
use App\Http\Controllers\Api\V1\SubsidiaryController;
use App\Http\Controllers\Api\V1\RegionController;
use App\Http\Controllers\Api\V1\TerritoryController;
use App\Http\Controllers\Api\V1\TeamController;
use App\Http\Controllers\Api\V1\ConfigurationController;
use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\ContactController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\TourPlanController;
use App\Http\Controllers\Api\V1\ActivityController;
use App\Http\Controllers\Api\V1\SampleMovementController;
use App\Http\Controllers\Api\V1\ObjectifController;
use App\Http\Controllers\Api\V1\CampaignController;
use App\Http\Controllers\Api\V1\KolProfileController;

Route::get('/login', fn() => response()->json(['message' => 'Non authentifié'], 401))->name('login');

Route::prefix('v1')->group(function () {

    // ── Authentification ──────────────────────────────────────
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
    Route::post('/auth/change-password', [AuthController::class, 'forceChangePassword']);

    // ── Endpoints protégés ────────────────────────────────────
    Route::middleware('auth:sanctum')->group(function () {
        
        // Création de compte (Admin)
        Route::post('/auth/invite', [AuthController::class, 'invite']);

        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);

        // ── Structure Organisationnelle ────────────────────────
        Route::apiResource('countries', CountryController::class);
        Route::apiResource('subsidiaries', SubsidiaryController::class);
        Route::apiResource('regions', RegionController::class);
        Route::apiResource('territories', TerritoryController::class);
        Route::apiResource('teams', TeamController::class);

        // ── Configuration (Tables paramétrables par l'Admin) ───
        // Ces endpoints permettent de personnaliser les types
        // sans toucher au code ni aux migrations.
        Route::prefix('config')->group(function () {
            // Account Types
            Route::get('account-types', [ConfigurationController::class, 'accountTypeIndex']);
            Route::post('account-types', [ConfigurationController::class, 'accountTypeStore']);
            Route::put('account-types/{id}', [ConfigurationController::class, 'accountTypeUpdate']);
            Route::delete('account-types/{id}', [ConfigurationController::class, 'accountTypeDestroy']);

            // Contact Types
            Route::get('contact-types', [ConfigurationController::class, 'contactTypeIndex']);
            Route::post('contact-types', [ConfigurationController::class, 'contactTypeStore']);
            Route::put('contact-types/{id}', [ConfigurationController::class, 'contactTypeUpdate']);
            Route::delete('contact-types/{id}', [ConfigurationController::class, 'contactTypeDestroy']);

            // Product Types
            Route::get('product-types', [ConfigurationController::class, 'productTypeIndex']);
            Route::post('product-types', [ConfigurationController::class, 'productTypeStore']);
            Route::put('product-types/{id}', [ConfigurationController::class, 'productTypeUpdate']);
            Route::delete('product-types/{id}', [ConfigurationController::class, 'productTypeDestroy']);
        });

        // ── Répertoire (Comptes & Contacts) ────────────────────
        Route::apiResource('accounts', AccountController::class);
        Route::apiResource('contacts', ContactController::class);
        Route::apiResource('categories', CategoryController::class);

        // ── Catalogue Produits ─────────────────────────────────
        Route::apiResource('products', ProductController::class);

        // ── Opérationnel Terrain ───────────────────────────────
        Route::apiResource('tour-plans', TourPlanController::class);
        Route::apiResource('activities', ActivityController::class);

        // ── Logistique Échantillons ────────────────────────────
        Route::get('sample-movements', [SampleMovementController::class, 'index']);
        Route::post('sample-movements', [SampleMovementController::class, 'store']);
        Route::get('sample-movements/{id}', [SampleMovementController::class, 'show']);

        // ── Objectifs & Performance ────────────────────────────
        Route::apiResource('objectifs', ObjectifController::class);
        
        // ── Campagnes & KOL (Extensions) ───────────────────────
        Route::apiResource('campaigns', CampaignController::class);
        Route::apiResource('kol-profiles', KolProfileController::class);
    });
});
