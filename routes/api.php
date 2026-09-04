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
use App\Http\Controllers\Api\V1\TaskController;
use App\Http\Controllers\Api\V1\OpportunityController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\SaleController;
use App\Http\Controllers\Api\V1\EventController;
use App\Http\Controllers\Api\V1\DocumentController;
use App\Http\Controllers\Api\V1\SyncController;
use App\Http\Controllers\Api\V1\CustomFieldController;
use App\Http\Controllers\Api\V1\ExpenseController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\PermissionController;
use App\Http\Controllers\Api\V1\ObjectifController;
use App\Http\Controllers\Api\V1\CampaignController;
use App\Http\Controllers\Api\V1\KolProfileController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\SettingController;

Route::get('/login', fn() => response()->json(['message' => 'Non authentifié'], 401))->name('login');

Route::prefix('v1')->group(function () {

    // ── Configuration Publique (Logo, Nom App) ────────────────
    Route::get('/settings', [SettingController::class, 'index']);

    // ── Authentification ──────────────────────────────────────
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
    Route::post('/auth/change-password', [AuthController::class, 'forceChangePassword']);

    // ── Endpoints protégés ────────────────────────────────────

    Route::middleware('auth:sanctum')->group(function () {

        // Création de compte (Admin)
        Route::post('/auth/invite', [AuthController::class, 'invite']);

        Route::apiResource('countries', CountryController::class);
        Route::apiResource('subsidiaries', SubsidiaryController::class);
        Route::apiResource('regions', RegionController::class);

        Route::apiResource('territories', TerritoryController::class);
        Route::apiResource('teams', TeamController::class);
        Route::apiResource('campaigns', CampaignController::class);
        Route::apiResource('kol-profiles', KolProfileController::class);

        // Configuration (Tables paramétrables par l'Admin/Manager)
        Route::prefix('config')->group(function () {
            Route::get('account-types', [ConfigurationController::class, 'accountTypeIndex']);
            Route::post('account-types', [ConfigurationController::class, 'accountTypeStore']);
            Route::put('account-types/{id}', [ConfigurationController::class, 'accountTypeUpdate']);
            Route::delete('account-types/{id}', [ConfigurationController::class, 'accountTypeDestroy']);

            Route::get('contact-types', [ConfigurationController::class, 'contactTypeIndex']);
            Route::post('contact-types', [ConfigurationController::class, 'contactTypeStore']);
            Route::put('contact-types/{id}', [ConfigurationController::class, 'contactTypeUpdate']);
            Route::delete('contact-types/{id}', [ConfigurationController::class, 'contactTypeDestroy']);

            Route::get('product-types', [ConfigurationController::class, 'productTypeIndex']);
            Route::post('product-types', [ConfigurationController::class, 'productTypeStore']);
            Route::put('product-types/{id}', [ConfigurationController::class, 'productTypeUpdate']);

            // Settings (Mise à jour)
            Route::post('settings', [SettingController::class, 'store']);
        });

        // ── Niveau 3 : Public Authentifié (Délégués et autres) ─────
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);

        // ── Notifications ──────────────────────────────────────────
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::post('/notifications/mark-all-as-read', [NotificationController::class, 'markAllAsRead']);
        Route::post('/notifications/{id}/mark-as-read', [NotificationController::class, 'markAsRead']);

        // ── Répertoire (Comptes & Contacts) ────────────────────
        Route::post('accounts/check-duplicates', [AccountController::class, 'checkDuplicates']);
        Route::apiResource('accounts', AccountController::class);

        Route::post('contacts/check-duplicates', [ContactController::class, 'checkDuplicates']);
        Route::apiResource('contacts', ContactController::class);

        Route::apiResource('categories', CategoryController::class);

        // ── Catalogue Produits ─────────────────────────────────
        Route::apiResource('products', ProductController::class);

        // ── Opérationnel Terrain (Visites, Tâches, Agenda) ──────
        Route::apiResource('tour-plans', TourPlanController::class);
        Route::apiResource('activities', ActivityController::class);
        Route::apiResource('tasks', TaskController::class);

        // ── Cycle Commercial (Opportunités, Commandes, Ventes) ──
        Route::apiResource('opportunities', OpportunityController::class);
        Route::apiResource('orders', OrderController::class);
        Route::apiResource('sales', SaleController::class);

        // ── Événements & Marketing ─────────────────────────────
        Route::apiResource('events', EventController::class);
        Route::apiResource('documents', DocumentController::class);

        // ── Logistique Échantillons ────────────────────────────
        Route::get('sample-movements', [SampleMovementController::class, 'index']);
        Route::post('sample-movements', [SampleMovementController::class, 'store']);
        Route::get('sample-movements/{id}', [SampleMovementController::class, 'show']);

        // -- Statistiques & Dashboards --
        Route::get('/analytics/dashboard', [\App\Http\Controllers\Api\V1\AnalyticsController::class, 'dashboard']);

        // ── Notes de Frais (Expenses) ──────────────────────────
        Route::apiResource('expenses', ExpenseController::class);
        Route::post('expenses/{expense}/submit', [ExpenseController::class, 'submit']);

        // ── Synchronisation Mobile Offline ───────────────────────
        Route::get('sync/pull', [SyncController::class, 'pull']);
        Route::post('sync/push', [SyncController::class, 'push']);

        // ── Administration & Configuration ─────────────────────
        Route::apiResource('custom-fields', CustomFieldController::class);
        Route::apiResource('roles', RoleController::class);
        Route::get('permissions', [PermissionController::class, 'index']);

        // ── Objectifs & Performance ────────────────────────────
        Route::apiResource('objectifs', ObjectifController::class);
    });
});
