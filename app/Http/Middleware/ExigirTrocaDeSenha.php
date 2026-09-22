<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usuário com `deve_trocar_senha` só navega para a tela de troca de senha
 * (e para o logout) até definir uma senha própria.
 */
class ExigirTrocaDeSenha
{
    private const ROTAS_LIVRES = ['senha.editar', 'senha.atualizar', 'logout'];

    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if ($usuario && $usuario->deve_trocar_senha && ! $request->routeIs(...self::ROTAS_LIVRES)) {
            return redirect()->route('senha.editar')
                ->with('aviso', 'Defina uma nova senha para continuar.');
        }

        return $next($request);
    }
}
