<?php

use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware([])->group(base_path('routes/payment.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            EnsureUserIsActive::class,
        ]);
        $middleware->alias(['admin' => EnsureUserIsAdmin::class]);
        $middleware->redirectUsersTo(fn (Request $request) => $request->user()?->is_admin ? route('admin.dashboard') : route('account.index'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || ($request->expectsJson() && ! $request->header('X-Inertia')),
        );
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            if (! app()->environment('local') || ! config('app.debug')) {
                if (in_array($response->getStatusCode(), [403, 404, 500, 503]) && ! $request->expectsJson()) {
                    return Inertia::render('Error', ['status' => $response->getStatusCode()])
                        ->toResponse($request)->setStatusCode($response->getStatusCode());
                }
            }
            if ($response->getStatusCode() === 419) {
                return back()->with('error', 'The page expired, please try again.');
            }

            return $response;
        });
    })->create();
