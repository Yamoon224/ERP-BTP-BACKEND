<?php

use App\Domains\Shared\Exceptions\DomainException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
        ]);

        // API pure, sans page de login web : ne jamais tenter de rediriger un
        // invité vers une route nommée `login` qui n'existe pas (sinon 500 au
        // lieu du 401 attendu).
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /*
         * Gestion centralisée des erreurs : un seul endroit décide de la forme
         * d'une réponse d'erreur, pour que tous les endpoints répondent de la
         * même manière et que les contrôleurs restent minces.
         *
         * Contrat de réponse d'erreur :
         *   { "message": string, "error_code": string, "context": object }
         * enrichi de "errors" pour les erreurs de validation (422).
         */
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // La validation garde la forme native de Laravel (`errors` par champ),
        // enrichie du code applicatif pour que le client n'ait qu'une seule
        // clé à tester pour router une erreur.
        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'message' => $exception->getMessage(),
                'error_code' => 'validation_failed',
                'errors' => $exception->errors(),
                'context' => (object) [],
            ], 422);
        });

        // Les exceptions métier portent elles-mêmes leur code HTTP et leur code
        // applicatif : les contrôleurs n'ont jamais à les intercepter.
        $exceptions->render(function (DomainException $exception, Request $request) {
            return $request->is('api/*') || $request->expectsJson()
                ? $exception->render()
                : null;
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'message' => 'Non authentifié — reconnectez-vous.',
                'error_code' => 'unauthenticated',
                'context' => (object) [],
            ], 401);
        });

        $exceptions->render(function (UnauthorizedException $exception, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'message' => "Vous n'avez pas les droits nécessaires pour cette action.",
                'error_code' => 'forbidden',
                'context' => (object) ['required_permissions' => $exception->getRequiredPermissions()],
            ], 403);
        });

        // Un modèle introuvable est un 404 métier, pas une fuite de nom de
        // classe Eloquent vers le client.
        $exceptions->render(function (ModelNotFoundException $exception, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'message' => 'Ressource introuvable.',
                'error_code' => 'not_found',
                'context' => (object) [],
            ], 404);
        });

        $exceptions->render(function (NotFoundHttpException $exception, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'message' => 'Ressource introuvable.',
                'error_code' => 'not_found',
                'context' => (object) [],
            ], 404);
        });
    })->create();
