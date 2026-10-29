<?php

use App\Http\Middleware\RestrictReferenceWrites;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Middleware de filtrage dynamique du périmètre organisationnel (header X-Organizational-Unit-Id)
        // Écriture des données de référence réservée à l'administration / direction
        $middleware->appendToGroup('api', RestrictReferenceWrites::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Keep detailed diagnostics in the server log, never in API responses (including debug mode).
        $exceptions->render(function (QueryException $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }
            $state = $e->errorInfo[0] ?? (string) $e->getCode();
            $missingSchema = in_array($state, ['42S02', '42S22', '42P01', '42703'], true)
                || ($state === 'HY000' && preg_match('/no such (table|column)/i', $e->getPrevious()?->getMessage() ?? ''));

            return response()->json([
                'message' => $missingSchema
                    ? 'La base de données doit être mise à jour. Faites exécuter php artisan migrate --force dans CRM-backend, puis réessayez.'
                    : 'Une erreur de base de données empêche cette opération. Réessayez ou contactez l’administrateur.',
                'code' => $missingSchema ? 'DATABASE_SCHEMA_NOT_READY' : 'DATABASE_ERROR',
            ], $missingSchema ? 503 : 500);
        });
        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e) {
            if ($request->is('api/*')) {
                return true;
            }

            return $request->expectsJson();
        });
    })->create();
