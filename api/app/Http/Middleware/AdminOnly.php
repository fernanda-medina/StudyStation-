<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminOnly
{
    /**
     * Deixa passar só quem é administrador. Os outros recebem 403 (proibido).
     * Esse middleware roda DEPOIS do auth:sanctum, então o usuário já está identificado.
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            $request->user()?->is_admin,
            403,
            'Apenas administradores podem alterar a biblioteca.'
        );

        return $next($request);
    }
}
