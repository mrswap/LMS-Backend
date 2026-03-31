<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Auth\AuthenticationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {


        $middleware->alias([
            'auth' => \App\Http\Middleware\Authenticate::class,
            'role' => \App\Http\Middleware\RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        /*
        |--------------------------------------------------------------------------
        | AUTHENTICATION
        |--------------------------------------------------------------------------
        */
        $exceptions->render(function (AuthenticationException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }
        });

        /*
        |--------------------------------------------------------------------------
        | MODEL NOT FOUND (MOST IMPORTANT)
        |--------------------------------------------------------------------------
        */
        $exceptions->render(function (ModelNotFoundException $e, $request) {
            if ($request->is('api/*')) {

                $model = class_basename($e->getModel());

                return response()->json([
                    'success' => false,
                    'message' => $model . ' not found'
                ], 404);
            }
        });

        /*
        |--------------------------------------------------------------------------
        | ROUTE NOT FOUND
        |--------------------------------------------------------------------------
        */
        $exceptions->render(function (NotFoundHttpException $e, $request) {

            if ($request->is('api/*')) {

                // 🔥 If it's actually a model error wrapped inside
                if ($e->getPrevious() instanceof ModelNotFoundException) {

                    $model = class_basename($e->getPrevious()->getModel());

                    return response()->json([
                        'success' => false,
                        'message' => $model . ' not found'
                    ], 404);
                }

                return response()->json([
                    'success' => false,
                    'message' => 'Route not found'
                ], 404);
            }
        });

    })
    ->create(); // ✅ VERY IMPORTANT