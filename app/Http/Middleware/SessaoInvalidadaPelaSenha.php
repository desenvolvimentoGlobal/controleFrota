<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;
use Symfony\Component\HttpFoundation\Response;

/**
 * AuthenticateSession do Laravel com a senha guardada POR USUÁRIO: trocar ou
 * redefinir a senha derruba as outras sessões e o "lembrar" daquela pessoa.
 * O original compara com o hash de quem estava na sessão antes; se a mesma
 * sessão passa a ser de outro usuário (actingAs nos testes, troca sem
 * logout), ele derrubaria o novo usuário sem motivo.
 */
class SessaoInvalidadaPelaSenha extends AuthenticateSession
{
    /** @param  Closure(Request): Response  $next */
    public function handle($request, Closure $next)
    {
        $usuarioId = $request->user()?->getAuthIdentifier();

        if ($usuarioId !== null && $request->hasSession() && $request->session()->get('password_hash_usuario') !== $usuarioId) {
            $request->session()->forget('password_hash_'.$this->auth->getDefaultDriver());
            $request->session()->put('password_hash_usuario', $usuarioId);
        }

        return parent::handle($request, $next);
    }
}
