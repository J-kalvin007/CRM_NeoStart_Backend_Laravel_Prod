<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\MediaController;
use App\Http\Controllers\Api\V1\SyncController;
use App\Http\Controllers\Api\V1\ActivityReportController;
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
use App\Http\Controllers\Api\V1\CustomFieldController;
use App\Http\Controllers\Api\V1\ExpenseController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\PermissionController;
use App\Http\Controllers\Api\V1\ObjectifController;
use App\Http\Controllers\Api\V1\CampaignController;
use App\Http\Controllers\Api\V1\KolProfileController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\SettingController;
use App\Http\Controllers\Api\V1\AppBrandingController;
use App\Http\Controllers\Api\V1\LeaveController;
use App\Http\Controllers\Api\V1\TrainingController;
use App\Http\Controllers\Api\V1\ConversationController;
use App\Http\Controllers\Api\V1\ClmAnalyticController;
use App\Http\Controllers\Api\V1\CoachingController;
use App\Http\Controllers\Api\V1\ObjectionController;

Route::get('/login', fn() => response()->json(['message' => 'Non authentifié'], 401))->name('login');

Route::prefix('v1')->group(function () {

    // ── Configuration Publique (Logo, Nom App) ────────────────
    Route::get('/settings', [SettingController::class, 'index']);

    // ── Branding public (accessible sans authentification) ───────
    Route::get('/branding', [AppBrandingController::class, 'show']);

    // ── Authentification ──────────────────────────────────────
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
    Route::post('/auth/change-password', [AuthController::class, 'forceChangePassword']);
    Route::post('/password/email', [AuthController::class, 'sendResetLinkEmail']);

    // ── Endpoints protégés ────────────────────────────────────

    Route::middleware('auth:sanctum')->group(function () {

    // Sync
    Route::get('/sync/pull', [SyncController::class, 'pull']);
    Route::post('/sync/push', [SyncController::class, 'push']);

    // Media
    Route::post('/media/upload', [MediaController::class, 'upload']);

    // Reports
    Route::put('/activities/{activity}/report', [ActivityReportController::class, 'update']);
    Route::post('/activities/{activity}/report/validate', [ActivityReportController::class, 'validateReport']);


        // Session & Préférences utilisateur
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/auth/toggle-otp', [AuthController::class, 'toggleOtp']);

        // Création de compte (Admin)
        Route::post('/auth/invite', [AuthController::class, 'invite']);

        Route::apiResource('countries', CountryController::class);
        Route::apiResource('subsidiaries', SubsidiaryController::class);
        Route::apiResource('regions', RegionController::class);
        Route::apiResource('territories', TerritoryController::class);
        Route::apiResource('zones', ZoneController::class);
        Route::apiResource('sectors', SectorController::class);
        Route::apiResource('teams', TeamController::class);
        Route::apiResource('campaigns', CampaignController::class);
        Route::apiResource('kol-profiles', KolProfileController::class);

        // Utilisateurs, Rôles & Permissions
        Route::apiResource('users', UserController::class);
        Route::apiResource('roles', RoleController::class);
        Route::get('permissions', [PermissionController::class, 'index']);


        // Configuration (Tables paramétrables par l'Admin/Manager)
        Route::prefix('organization')->group(function () {
            Route::get('tree', [\App\Http\Controllers\Api\V1\OrganizationController::class, 'tree']);
            Route::get('flat', [\App\Http\Controllers\Api\V1\OrganizationController::class, 'flat']);
        });

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

            // Settings (Mise à jour générique)
            Route::post('settings', [SettingController::class, 'store']);
        });

        // ── Branding (Nom, Logo, Favicon) ─────────────────────────
        Route::prefix('branding')->group(function () {
            Route::put('name', [AppBrandingController::class, 'updateName']);
            Route::post('logo', [AppBrandingController::class, 'uploadLogo']);
            Route::delete('logo', [AppBrandingController::class, 'deleteLogo']);
            Route::post('favicon', [AppBrandingController::class, 'uploadFavicon']);
            Route::delete('favicon', [AppBrandingController::class, 'deleteFavicon']);
        });

        // ── Niveau 3 : Public Authentifié (Délégués et autres) ─────
        // FCM Token
        Route::post('/user/fcm-token', [AuthController::class, 'updateFcmToken']);

        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);

        // ── Notifications ──────────────────────────────────────────
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::post('/notifications', [NotificationController::class, 'store']);
        Route::post('/notifications/mark-all-as-read', [NotificationController::class, 'markAllAsRead']);
        Route::post('/notifications/{id}/mark-as-read', [NotificationController::class, 'markAsRead']);

        // ── Répertoire (Comptes & Contacts) ────────────────────
        Route::get('validations/pending', [App\Http\Controllers\Api\V1\ValidationController::class, 'index']);
        Route::post('validations/{type}/{id}/approve', [App\Http\Controllers\Api\V1\ValidationController::class, 'approve']);
        Route::post('validations/{type}/{id}/reject', [App\Http\Controllers\Api\V1\ValidationController::class, 'reject']);

        Route::post('accounts/check-duplicates', [AccountController::class, 'checkDuplicates']);
        Route::apiResource('accounts', AccountController::class);
        Route::apiResource('specialities', App\Http\Controllers\Api\V1\SpecialityController::class);
        Route::apiResource('segmentations', App\Http\Controllers\Api\V1\SegmentationController::class);
        Route::apiResource('potentials', App\Http\Controllers\Api\V1\PotentialController::class);
        Route::apiResource('influences', App\Http\Controllers\Api\V1\InfluenceController::class);
        Route::apiResource('galenic-forms', App\Http\Controllers\Api\V1\GalenicFormController::class);
        Route::apiResource('activity-types', App\Http\Controllers\Api\V1\ActivityTypeController::class);
        Route::apiResource('activity-results', App\Http\Controllers\Api\V1\ActivityResultController::class);
        Route::apiResource('event-formats', App\Http\Controllers\Api\V1\EventFormatController::class);


        Route::post('contacts/check-duplicates', [ContactController::class, 'checkDuplicates']);
        Route::post('contacts/arbitrage', [ContactController::class, 'arbitrage']);
        Route::apiResource('contacts', ContactController::class);

        Route::apiResource('categories', CategoryController::class);

        // ── Catalogue Produits ─────────────────────────────────
        Route::apiResource('products', ProductController::class);
        Route::get('products/{product}/objections', [ObjectionController::class, 'indexByProduct']);

        // ── Opérationnel Terrain (Visites, Tâches, Agenda) ──────
        Route::post('tour-plans/optimize', [\App\Http\Controllers\Api\V1\TourPlanController::class, 'optimize']);
        Route::apiResource('tour-plans', TourPlanController::class);
        Route::post('activities/duo', [CoachingController::class, 'scheduleDuoVisit']);
        Route::apiResource('activities', ActivityController::class);
        Route::post('activities/{activity}/clm-analytics', [ClmAnalyticController::class, 'store']);
        Route::post('activities/{activity}/objections', [ObjectionController::class, 'storeForActivity']);
        Route::apiResource('tasks', TaskController::class);

        // ── Cycle Commercial (Opportunités, Commandes, Ventes) ──
        Route::apiResource('opportunities', OpportunityController::class);
        Route::apiResource('orders', OrderController::class);
        Route::apiResource('sales', SaleController::class);

        // ── Événements & Marketing ─────────────────────────────
        Route::apiResource('events', EventController::class);
        Route::apiResource('documents', DocumentController::class);
        Route::apiResource('digital-supports', \App\Http\Controllers\Api\V1\DigitalSupportController::class);
        Route::apiResource('pharmacovigilance-reports', \App\Http\Controllers\Api\V1\PharmacovigilanceReportController::class);


        // ── Logistique & Stocks Échantillons ────────────────────
        Route::get('my-stock', [SampleMovementController::class, 'myStock']);
        Route::apiResource('sample-movements', SampleMovementController::class);
        Route::apiResource('stocks', SampleMovementController::class);

        // -- Statistiques & Dashboards --
        // Route::get('/analytics/dashboard', [\App\Http\Controllers\Api\V1\AnalyticsController::class, 'dashboard']);
        Route::get('/analytics/team-performance', [\App\Http\Controllers\Api\V1\AnalyticsController::class, 'teamPerformance']);
        Route::get('/analytics/edetailing-summary', [\App\Http\Controllers\Api\V1\AnalyticsController::class, 'edetailingSummary']);
        Route::get('/export/performance', [\App\Http\Controllers\Api\V1\AnalyticsController::class, 'exportPerformance']);
        Route::get('/export/expenses', [\App\Http\Controllers\Api\V1\AnalyticsController::class, 'exportExpenses']);
        Route::get('/marketing/objections-stats', [\App\Http\Controllers\Api\V1\AnalyticsController::class, 'objectionsStats']);
        Route::get('/users/locations', [\App\Http\Controllers\Api\V1\AnalyticsController::class, 'usersLocations']);
        Route::get('/supervisor/alerts', [\App\Http\Controllers\Api\V1\AnalyticsController::class, 'alerts']);
        Route::get('/supervisor/dashboard', [\App\Http\Controllers\Api\V1\SupervisorDashboardController::class, 'dashboard']);
        Route::get('/supervisor/team-planning', [\App\Http\Controllers\Api\V1\SupervisorDashboardController::class, 'teamPlanning']);
        Route::get('/supervisor/missing-reports', [\App\Http\Controllers\Api\V1\SupervisorReportController::class, 'index']);
        Route::post('/supervisor/remind-missing-reports', [\App\Http\Controllers\Api\V1\SupervisorReportController::class, 'remind']);
        Route::get('/trainings', [\App\Http\Controllers\Api\V1\TrainingController::class, 'index']);
        Route::get('/kols', [\App\Http\Controllers\Api\V1\KolController::class, 'index']);

        // -- Coaching & Visites en Duo --
        Route::get('/coaching/delegates/{id}', [CoachingController::class, 'getSessions']);
        Route::post('/coaching-sessions', [CoachingController::class, 'storeSession']);
        Route::put('/coaching-sessions/{id}/complete', [CoachingController::class, 'completeSession']);

        // ── Notes de Frais (Expenses) ──────────────────────────
        Route::apiResource('expenses', ExpenseController::class);
        Route::post('expenses/{expense}/submit', [ExpenseController::class, 'submit']);
        Route::post('expenses/{expense}/validate', [ExpenseController::class, 'validateExpense']);
        Route::post('expenses/{expense}/reject', [ExpenseController::class, 'rejectExpense']);
        Route::post('expenses/{expense}/reimburse', [ExpenseController::class, 'reimburseExpense']);

        // ── Synchronisation Mobile Offline ───────────────────────
        Route::get('sync/pull', [SyncController::class, 'pull']);
        Route::post('sync/push', [SyncController::class, 'push']);

        // ── Administration & Configuration ─────────────────────
        Route::apiResource('custom-fields', CustomFieldController::class);
        Route::apiResource('roles', RoleController::class);
        Route::get('permissions', [PermissionController::class, 'index']);

        // ── Objectifs & Performance ────────────────────────────
        Route::get('me/objectifs-summary', [ObjectifController::class, 'summary']);
        Route::apiResource('objectifs', ObjectifController::class);

        // ── Congés (Leaves) ────────────────────────────────────
        Route::get('leaves', [LeaveController::class, 'index']);
        Route::post('leaves', [LeaveController::class, 'store']);
        Route::patch('leaves/{id}/status', [LeaveController::class, 'updateStatus']);

        // ── Demandes & Escalade ────────────────────────────────
        Route::post('requests', [\App\Http\Controllers\Api\V1\RequestController::class, 'store']);

        // ── Formations (Trainings) ─────────────────────────────
        // Route::get('trainings', [TrainingController::class, 'index']);
        Route::get('trainings/{id}', [TrainingController::class, 'show']);
        Route::patch('trainings/{id}/progress', [TrainingController::class, 'updateProgress']);

        // ── Messagerie Interne ─────────────────────────────────
        Route::get('conversations', [ConversationController::class, 'index']);
        Route::get('conversations/{id}/messages', [ConversationController::class, 'messages']);
        Route::post('conversations/{id}/messages', [ConversationController::class, 'sendMessage']);


        // ── Approbation de dépenses (PATCH) ─────────────────────
        Route::patch('expenses/{expense}/status', [ExpenseController::class, 'updateStatus']);

        // ── KOL alias (compatibilité mobile) ─────────────────────
        // Route::get('kols', [KolProfileController::class, 'index']);
 
         // ── Performance Équipe ─────────────────────────────────
         // Route::get('analytics/team-performance', [\App\Http\Controllers\Api\V1\ManagerDashboardController::class, 'teamPerformance']);
    });
});
