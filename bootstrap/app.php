<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'tenant' => \App\Http\Middleware\InitializeTenancyFromUser::class,
            'abilities' => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            return match (true) {
                $e instanceof ValidationException => response()->json(
                    ['message' => $e->validator->errors()->first()],
                    422
                ),

                $e instanceof AuthenticationException => response()->json(
                    ['message' => $e->getMessage()],
                    401
                ),

                $e instanceof HttpExceptionInterface => response()->json(
                    ['message' => $e->getStatusCode() === 404
                        ? 'Resource not found.'
                        : ($e->getMessage() ?: 'Request failed.')],
                    $e->getStatusCode()
                ),

                default => response()->json(
                    ['message' => config('app.debug') ? $e->getMessage() : 'Server error.'],
                    500
                ),
            };
        });
    })->create();
