<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Deja pasar solo al personal. Se apoya en el rol y además en la habilidad
 * del token, para que un token de la app de clientes no sirva acá aunque la
 * cuenta tenga permisos.
 */
class EnsureIsStaff
{
    public function handle(Request $request, Closure $next, ?string $role = null): Response
    {
        $user = $request->user();

        $allowed = $role === 'admin'
            ? $user?->isAdmin()
            : $user?->isStaff();

        if (! $allowed || ! $request->user()->tokenCan('staff')) {
            return response()->json([
                'message' => 'No tenés permisos para esta acción.',
            ], 403);
        }

        return $next($request);
    }
}
