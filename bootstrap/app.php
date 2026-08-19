<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

/*
| Este proyecto es solo API: no hay rutas web ni vistas. Todo lo que no
| coincida con una ruta de /api responde 404 en JSON.
*/

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Sin rutas web no hay a dónde redirigir: los invitados reciben 401 JSON.
        $middleware->redirectGuestsTo(fn () => null);

        $middleware->alias(['staff' => \App\Http\Middleware\EnsureIsStaff::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Siempre JSON, incluso cuando el navegador pide HTML.
        $exceptions->shouldRenderJsonWhen(fn () => true);
    })->create();
