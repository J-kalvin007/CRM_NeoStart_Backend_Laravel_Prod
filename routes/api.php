<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\MediaController;
use App\Http\Controllers\Api\V1\SyncController;
use App\Http\Controllers\Api\V1\ActivityReportController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CountryController;
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
use App\Http\Controllers\Api\V1\ObjectifTypeController;
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
use App\Http\Controllers\Api\V1\ValidationController;
use App\Http\Controllers\Api\V1\SupervisorDashboardController;
use App\Http\Controllers\Api\V1\RequestController;

use App\Http\Controllers\Api\V1\LaboratoryController;
use App\Http\Controllers\Api\V1\TherapeuticClassController;
Route::get('/login', fn() => response()->json(['message' => 'Non authentifié'], 401))->name('login');

Route::prefix('v1')->group(function () {

    // ── Configuration Publique (Logo, Nom App) ────────────────
    Route::get('/settings', [SettingController::class, 'index']);

    // ── Branding public (accessible sans authentification) ───────
    Route::get('/branding', [AppBrandingController::class, 'show']);

    // ── Authentification ──────────────────────────────────────
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
    Route::post('/resend-otp', [AuthController::class, 'resendOtp']);
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
        Route::patch('campaigns/{campaign}/status', [CampaignController::class, 'updateStatus']);
        Route::post('campaigns/{campaign}/products', [CampaignController::class, 'attachProduct']);
        Route::delete('campaigns/{campaign}/products/{product}', [CampaignController::class, 'detachProduct']);
        Route::post('campaigns/{campaign}/organizational-units', [CampaignController::class, 'attachOrganizationalUnit']);
        Route::delete('campaigns/{campaign}/organizational-units/{unit}', [CampaignController::class, 'detachOrganizationalUnit']);
        Route::apiResource('campaigns', CampaignController::class);
        Route::apiResource('kol-profiles', KolProfileController::class);

        // Utilisateurs, Rôles & Permissions
        Route::get('users/locations', [\App\Http\Controllers\Api\V1\AnalyticsController::class, 'usersLocations']);
        Route::post('me/location', [\App\Http\Controllers\Api\V1\LocationController::class, 'store']);
        Route::get('users/{id}/locations', [\App\Http\Controllers\Api\V1\LocationController::class, 'history']);
        Route::post('users/bulk-update-country', [UserController::class, 'bulkUpdateCountry']);
        Route::apiResource('users', UserController::class);
        Route::apiResource('roles', RoleController::class);
        Route::get('permissions', [PermissionController::class, 'index']);
        Route::get('permissions/grouped', [PermissionController::class, 'grouped']);
        Route::post('roles/{roleId}/permissions', [PermissionController::class, 'assignToRole']);
        Route::get('roles/{roleId}/permissions', [PermissionController::class, 'getRolePermissions']);


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
        Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);

        // ── Superviseur ──────────────────────────────────────────
        Route::get('/supervisor/dashboard', [SupervisorDashboardController::class, 'dashboard']);
        Route::get('/supervisor/team-planning', [SupervisorDashboardController::class, 'teamPlanning']);

        // ── Répertoire (Comptes & Contacts) ────────────────────
        Route::get('validations/pending', [ValidationController::class, 'index']);
        Route::post('validations/bulk', [ValidationController::class, 'bulk']);
        Route::post('validations/{type}/{id}/approve', [ValidationController::class, 'approve']);
        Route::post('validations/{type}/{id}/reject', [ValidationController::class, 'reject']);

        Route::post('accounts/check-duplicates', [AccountController::class, 'checkDuplicates']);
        Route::post('accounts/{account}/valider', [AccountController::class, 'validateAccount']);
        Route::post('accounts/{account}/rejeter', [AccountController::class, 'rejectAccount']);
        Route::post('accounts/{account}/validate', [AccountController::class, 'validateAccount']);
        Route::apiResource('accounts', AccountController::class);
        Route::apiResource('specialities', App\Http\Controllers\Api\V1\SpecialityController::class);
        Route::apiResource('segmentations', App\Http\Controllers\Api\V1\SegmentationController::class);
        Route::apiResource('potentials', App\Http\Controllers\Api\V1\PotentialController::class);
        Route::apiResource('influences', App\Http\Controllers\Api\V1\InfluenceController::class);
        Route::apiResource('galenic-forms', App\Http\Controllers\Api\V1\GalenicFormController::class);
        Route::apiResource('activity-types', App\Http\Controllers\Api\V1\ActivityTypeController::class);
        Route::apiResource('activity-results', App\Http\Controllers\Api\V1\ActivityResultController::class);
        Route::apiResource('event-formats', App\Http\Controllers\Api\V1\EventFormatController::class);
        Route::apiResource('duo-activity-types', App\Http\Controllers\Api\V1\DuoActivityTypeController::class);
        Route::apiResource('trio-activity-types', App\Http\Controllers\Api\V1\TrioActivityTypeController::class);


        Route::post('contacts/check-duplicates', [ContactController::class, 'checkDuplicates']);
        Route::post('contacts/arbitrage', [ContactController::class, 'arbitrage']);
        Route::post('contacts/import', [\App\Http\Controllers\Api\V1\ContactImportController::class, 'import']);
        Route::get('contacts/import/template', [\App\Http\Controllers\Api\V1\ContactImportController::class, 'downloadTemplate']);
        Route::delete('contacts/bulk-delete', [ContactController::class, 'bulkDelete']);
        Route::post('contacts/{contact}/valider', [ContactController::class, 'validateContact']);
        Route::post('contacts/{contact}/rejeter', [ContactController::class, 'rejectContact']);
        Route::post('contacts/{contact}/validate', [ContactController::class, 'validateContact']);
        Route::apiResource('contacts', ContactController::class);

        // ── Import Établissements ──────────────────────────────────────────
        Route::post('accounts/import', [\App\Http\Controllers\Api\V1\AccountImportController::class, 'import']);
        Route::get('accounts/import/template', [\App\Http\Controllers\Api\V1\AccountImportController::class, 'downloadTemplate']);
        
        // ── Import Produits ────────────────────────────────────────────────
        Route::post('products/import', [\App\Http\Controllers\Api\V1\ProductImportController::class, 'import']);
        Route::get('products/import/template', [\App\Http\Controllers\Api\V1\ProductImportController::class, 'downloadTemplate']);

        Route::apiResource('categories', CategoryController::class);

        // ── Catalogue Produits ─────────────────────────────────
        Route::apiResource('laboratories', LaboratoryController::class);
        Route::apiResource('therapeutic-classes', TherapeuticClassController::class);
        Route::apiResource('products', ProductController::class);
        Route::get('products/{product}/objections', [ObjectionController::class, 'indexByProduct']);

        // ── Opérationnel Terrain (Visites, Tâches, Agenda) ──────
        Route::post('tour-plans/optimize', [\App\Http\Controllers\Api\V1\TourPlanController::class, 'optimize']);
        Route::apiResource('tour-plans', TourPlanController::class);
        Route::post('activities/trio', [ActivityController::class, 'storeTrio']);
        Route::post('trio', [ActivityController::class, 'storeTrio']);
        Route::post('activities/{activity}/validate-trio', [ActivityController::class, 'validateTrio']);
        Route::post('activities/{activity}/reject-trio', [ActivityController::class, 'rejectTrio']);
        Route::post('trio/{id}/valider', [ActivityController::class, 'validateTrio']);
        Route::post('trio/{id}/rejeter', [ActivityController::class, 'rejectTrio']);
        Route::post('activities/duo', [ActivityController::class, 'storeDuo']);
        Route::post('duo', [ActivityController::class, 'storeDuo']);
        Route::post('activities/{activity}/accept-duo', [ActivityController::class, 'acceptDuo']);
        Route::post('activities/{activity}/refuse-duo', [ActivityController::class, 'refuseDuo']);
        Route::post('activities/{activity}/join-duo', [ActivityController::class, 'joinDuo']);
        Route::patch('activities/{activity}/duo', [ActivityController::class, 'updateDuo']);
        Route::delete('activities/{activity}/duo', [ActivityController::class, 'cancelDuo']);
        Route::post('duo/{id}/accepter', [ActivityController::class, 'acceptDuo']);
        Route::post('duo/{id}/refuser', [ActivityController::class, 'refuseDuo']);
        Route::post('activities/{activity}/validate', [ActivityController::class, 'validateActivity']);
        Route::post('activities/bulk-validate', [ActivityController::class, 'validateActivitiesBulk']);
        Route::delete('activities/bulk-delete', [ActivityController::class, 'bulkDestroy']);
        Route::post('activities/bulk-delete', [ActivityController::class, 'bulkDestroy']);
        Route::post('activities/{activity}/reschedule', [ActivityController::class, 'reschedule']);
        Route::post('activities/{activity}/start', [ActivityController::class, 'startVisit']);
        Route::post('activities/{activity}/complete', [ActivityController::class, 'completeVisit']);
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
        Route::get('contacts/{contact}/presented-documents', [DocumentController::class, 'presentedToContact']);
        Route::apiResource('documents', DocumentController::class);
        Route::apiResource('digital-supports', \App\Http\Controllers\Api\V1\DigitalSupportController::class);
        Route::apiResource('pharmacovigilance-reports', \App\Http\Controllers\Api\V1\PharmacovigilanceReportController::class);


        // ── Logistique & Stocks Échantillons ────────────────────
        Route::get('my-stock', [SampleMovementController::class, 'myStock']);
        Route::apiResource('sample-movements', SampleMovementController::class);
        Route::apiResource('stocks', SampleMovementController::class);

        // Demandes de réassort d'échantillons
        Route::get('sample-requests', [\App\Http\Controllers\Api\V1\SampleRequestController::class, 'index']);
        Route::post('sample-requests', [\App\Http\Controllers\Api\V1\SampleRequestController::class, 'store']);
        Route::put('sample-requests/{id}', [\App\Http\Controllers\Api\V1\SampleRequestController::class, 'update']);
        Route::delete('sample-requests/{id}', [\App\Http\Controllers\Api\V1\SampleRequestController::class, 'destroy']);
        Route::post('sample-requests/{id}/approve', [\App\Http\Controllers\Api\V1\SampleRequestController::class, 'approve']);
        Route::post('sample-requests/{id}/reject', [\App\Http\Controllers\Api\V1\SampleRequestController::class, 'reject']);

        // -- Statistiques & Dashboards --
        Route::get('/analytics/executive', [\App\Http\Controllers\Api\V1\AnalyticsController::class, 'executiveSummary']);
        // Route::get('/analytics/dashboard', ...) — méthode AnalyticsController::dashboard() inexistante, non enregistrée
        Route::get('/analytics/team-performance', [\App\Http\Controllers\Api\V1\AnalyticsController::class, 'teamPerformance']);
        Route::get('/analytics/edetailing-summary', [\App\Http\Controllers\Api\V1\AnalyticsController::class, 'edetailingSummary']);
        Route::get('/export/performance', [\App\Http\Controllers\Api\V1\AnalyticsController::class, 'exportPerformance']);
        Route::get('/export/expenses', [\App\Http\Controllers\Api\V1\AnalyticsController::class, 'exportExpenses']);
        Route::get('/marketing/objections-stats', [\App\Http\Controllers\Api\V1\AnalyticsController::class, 'objectionsStats']);
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
        Route::get('/coaching-sessions', [CoachingController::class, 'index']);
        Route::put('/coaching-sessions/{id}/complete', [CoachingController::class, 'completeSession']);
        Route::post('/coaching-sessions/{id}/refuse', [CoachingController::class, 'refuseSession']);
        Route::get('/coaching-sessions/{id}', [CoachingController::class, 'show']);
        Route::put('/coaching-sessions/{id}', [CoachingController::class, 'update']);
        Route::delete('/coaching-sessions/{id}', [CoachingController::class, 'destroy']);

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
        Route::get('objectifs/{objectif}/history', [ObjectifController::class, 'history']);
        Route::apiResource('objectifs', ObjectifController::class);
        Route::apiResource('objectif-types', ObjectifTypeController::class);
        Route::get('indicators', [\App\Http\Controllers\Api\V1\IndicatorController::class, 'index']);

        // ── Congés (Leaves) ────────────────────────────────────
        Route::get('leaves', [LeaveController::class, 'index']);
        Route::post('leaves', [LeaveController::class, 'store']);
        Route::put('leaves/{id}', [LeaveController::class, 'update']);
        Route::delete('leaves/{id}', [LeaveController::class, 'destroy']);
        Route::patch('leaves/{id}/status', [LeaveController::class, 'updateStatus']);
        Route::post('leaves/{id}/validate', [LeaveController::class, 'validateLeave']);
        Route::post('leaves/{id}/reject', [LeaveController::class, 'rejectLeave']);

        // ── Demandes & Escalade ────────────────────────────────
        Route::apiResource('requests', \App\Http\Controllers\Api\V1\RequestController::class)->except(['show']);

        // ── Formations (Trainings) ─────────────────────────────
        // Route::get('trainings', [TrainingController::class, 'index']);
        Route::get('trainings/{id}', [TrainingController::class, 'show']);
        Route::patch('trainings/{id}/progress', [TrainingController::class, 'updateProgress']);

        // ── Messagerie Interne ─────────────────────────────────
        Route::get('conversations', [ConversationController::class, 'index']);
        Route::post('conversations', [ConversationController::class, 'store']);
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
