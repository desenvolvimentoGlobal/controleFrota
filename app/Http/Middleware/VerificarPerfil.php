<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe rotas pelo código do perfil do usuário autenticado.
 *
 *   Route::middleware(['auth', 'perfil:admin'])->group(...);
 *   Route::middleware(['auth', 'perfil:admin,gestor'])->group(...);
 *
 * Códigos: admin, financeiro, gestor, geral (ver App\Enums\PerfilUsuario).
 */
class VerificarPerfil
{
    public function handle(Request $request, Closure $next, string ...$perfisPermitidos): Response
    {
        if (! $request->user()) {
            return redirect()->route('login');
        }

        if (! in_array($request->user()->perfil?->codigo, $perfisPermitidos, true)) {
            abort(403, 'Seu perfil não tem permissão para acessar esta área.');
        }

        return $next($request);
    }
}
