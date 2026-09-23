<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * O login recusa usuário inativo, mas uma sessão aberta (ou o cookie
 * "manter conectado") continuaria valendo depois da inativação. Aqui ela
 * cai na próxima requisição.
 */
class GarantirUsuarioAtivo
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if ($usuario && ! $usuario->ativo) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'erro' => 'Usuário inativo.'], 401);
            }

            return redirect()->route('login')->withErrors(['acesso' => 'Seu usuário foi inativado. Procure o administrador.']);
        }

        return $next($request);
    }
}
