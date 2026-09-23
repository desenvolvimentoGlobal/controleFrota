<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\TokenIntegracao;
use App\Support\RespostaApi;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autenticação de SISTEMA da API `/api/integracao/v1` por Bearer token
 * conferido contra o hash em `tokens_integracao` (padrão dos irmãos).
 *
 *   ->middleware('integracao.api')             basta o token valer
 *   ->middleware('integracao.api:alocacoes')   exige o escopo
 *
 * Escopo fechado por padrão: endpoint sensível novo não se abre sozinho
 * para os consumidores que já existem.
 */
class AutenticarIntegracao
{
    public function handle(Request $request, Closure $next, string ...$escoposExigidos): Response
    {
        $token = TokenIntegracao::autenticar($request->bearerToken());

        if ($token === null) {
            return RespostaApi::erro('nao_autenticado', 'Token de integração ausente, inválido ou revogado.', 401);
        }

        // Gravado antes do escopo: a tentativa recusada também é uso.
        $token->forceFill(['ultimo_uso_em' => now()])->save();

        foreach ($escoposExigidos as $escopo) {
            if (! $token->temEscopo($escopo)) {
                return RespostaApi::erro('permissao_negada', "Este token não tem o escopo \"{$escopo}\".", 403);
            }
        }

        $request->attributes->set('token_integracao', $token);

        return $next($request);
    }
}
