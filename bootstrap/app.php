<?php

use App\Http\Middleware\AsegurarUsuarioActivo;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
        $middleware->redirectGuestsTo('/login');
        $middleware->web(append: [AsegurarUsuarioActivo::class]);
        // Meta llama al webhook sin token CSRF; se autentica con la firma X-Hub-Signature-256.
        $middleware->validateCsrfTokens(except: ['webhooks/whatsapp']);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
