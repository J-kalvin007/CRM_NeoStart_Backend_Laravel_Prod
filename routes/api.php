<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CountryController;
use App\Http\Controllers\Api\V1\SubsidiaryController;
use App\Http\Controllers\Api\V1\RegionController;
use App\Http\Controllers\Api\V1\TerritoryController;
use App\Http\Controllers\Api\V1\ZoneController;
use App\Http\Controllers\Api\V1\SectorController;
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
use App\Http\Controllers\Api\V1\UserController;
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
use App\Http\Controllers\Api\V1\LeaveController;
use App\Http\Controllers\Api\V1\TrainingController;
use App\Http\Controllers\Api\V1\ConversationController;
use App\Http\Controllers\Api\V1\ValidationController;
use App\Http\Controllers\Api\V1\SupervisorDashboardController;

use App\Http\Controllers\Api\V1\LaboratoryController;
use App\Http\Controllers\Api\V1\TherapeuticClassController;

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
        Route::apiResource('zones', ZoneController::class);
        Route::apiResource('sectors', SectorController::class);
        Route::apiResource('teams', TeamController::class);
        Route::patch('campaigns/{campaign}/status', [CampaignController::class, 'updateStatus']);
        Route::post('campaigns/{campaign}/products', [CampaignController::class, 'attachProduct']);
        Route::delete('campaigns/{campaign}/products/{product}', [CampaignController::class, 'detachProduct']);
        Route::post('campaigns/{campaign}/teams', [CampaignController::class, 'attachTeam']);
        Route::delete('campaigns/{campaign}/teams/{team}', [CampaignController::class, 'detachTeam']);
        Route::apiResource('campaigns', CampaignController::class);
        Route::apiResource('kol-profiles', KolProfileController::class);

        // Utilisateurs, Rôles & Permissions
        Route::apiResource('users', UserController::class);
        Route::apiResource('roles', RoleController::class);
        Route::get('permissions', [PermissionController::class, 'index']);

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
            Route::delete('product-types/{id}', [ConfigurationController::class, 'productTypeDestroy']);

            // Settings (Mise à jour)
            Route::post('settings', [SettingController::class, 'store']);
        });

        // ── Niveau 3 : Public Authentifié (Délégués et autres) ─────
        // FCM Token
        Route::post('/user/fcm-token', [AuthController::class, 'updateFcmToken']);

        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);

        // ── Notifications ──────────────────────────────────────────
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::post('/notifications/mark-all-as-read', [NotificationController::class, 'markAllAsRead']);
        Route::post('/notifications/{id}/mark-as-read', [NotificationController::class, 'markAsRead']);

        // ── Superviseur ──────────────────────────────────────────
        Route::get('/supervisor/dashboard', [SupervisorDashboardController::class, 'dashboard']);

        // ── Répertoire (Comptes & Contacts) ────────────────────
        Route::get('validations/pending', [ValidationController::class, 'indexPending']);
        Route::post('validations/{type}/{id}/approve', [ValidationController::class, 'approve']);
        Route::post('validations/{type}/{id}/reject', [ValidationController::class, 'reject']);

        Route::post('accounts/check-duplicates', [AccountController::class, 'checkDuplicates']);
        Route::apiResource('accounts', AccountController::class);

        Route::post('contacts/check-duplicates', [ContactController::class, 'checkDuplicates']);
        Route::apiResource('contacts', ContactController::class);

        Route::apiResource('categories', CategoryController::class);

        // ── Catalogue Produits ─────────────────────────────────
        Route::apiResource('laboratories', LaboratoryController::class);
        Route::apiResource('therapeutic-classes', TherapeuticClassController::class);
        Route::apiResource('products', ProductController::class);

        // ── Opérationnel Terrain (Visites, Tâches, Agenda) ──────
        Route::apiResource('tour-plans', TourPlanController::class);
        Route::post('activities/{activity}/validate', [ActivityController::class, 'validateActivity']);
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

        // Demandes de réassort d'échantillons
        Route::get('sample-requests', [\App\Http\Controllers\Api\V1\SampleRequestController::class, 'index']);
        Route::post('sample-requests', [\App\Http\Controllers\Api\V1\SampleRequestController::class, 'store']);
        Route::post('sample-requests/{id}/approve', [\App\Http\Controllers\Api\V1\SampleRequestController::class, 'approve']);
        Route::post('sample-requests/{id}/reject', [\App\Http\Controllers\Api\V1\SampleRequestController::class, 'reject']);

        // -- Statistiques & Dashboards --
        Route::get('/analytics/executive', [\App\Http\Controllers\Api\V1\AnalyticsController::class, 'executiveSummary']);
        Route::get('/analytics/dashboard', [\App\Http\Controllers\Api\V1\AnalyticsController::class, 'dashboard']);
        Route::get('/analytics/team-performance', [\App\Http\Controllers\Api\V1\AnalyticsController::class, 'teamPerformance']);
        Route::get('/users/locations', [\App\Http\Controllers\Api\V1\AnalyticsController::class, 'usersLocations']);
        Route::get('/trainings', [\App\Http\Controllers\Api\V1\TrainingController::class, 'index']);
        Route::get('/kols', [\App\Http\Controllers\Api\V1\KolController::class, 'index']);

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
        Route::get('indicators', [\App\Http\Controllers\Api\V1\IndicatorController::class, 'index']);

        // ── Congés (Leaves) ────────────────────────────────────
        Route::get('leaves', [LeaveController::class, 'index']);
        Route::post('leaves', [LeaveController::class, 'store']);
        Route::patch('leaves/{id}/status', [LeaveController::class, 'updateStatus']);

        // ── Formations (Trainings) ─────────────────────────────
        Route::get('trainings', [TrainingController::class, 'index']);
        Route::get('trainings/{id}', [TrainingController::class, 'show']);
        Route::patch('trainings/{id}/progress', [TrainingController::class, 'updateProgress']);

        // ── Messagerie Interne ─────────────────────────────────
        Route::get('conversations', [ConversationController::class, 'index']);
        Route::get('conversations/{id}/messages', [ConversationController::class, 'messages']);
        Route::post('conversations/{id}/messages', [ConversationController::class, 'sendMessage']);

        // ── Stock délégué (alias sample-movements avec filtre user) ─
        Route::get('my-stock', [SampleMovementController::class, 'myStock']);

        // ── Approbation de dépenses (PATCH) ─────────────────────
        Route::patch('expenses/{expense}/status', [ExpenseController::class, 'updateStatus']);

        // ── KOL alias (compatibilité mobile) ─────────────────────
        Route::get('kols', [KolProfileController::class, 'index']);

        // ── Performance Équipe ─────────────────────────────────
        Route::get('analytics/team-performance', [\App\Http\Controllers\Api\V1\ManagerDashboardController::class, 'teamPerformance']);
    });
});
