<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Memoriza os filtros das listagens na sessão (por rota) e os devolve quando o
 * usuário volta de uma página aberta A PARTIR daquela listagem.
 *
 * Regras (herdadas do gestaoPessoas):
 *  1. Mescla chave a chave: valor → aplica; vazia → limpa; ausente → mantém.
 *  2. Só vale de uma página para a outra. Chegando de fora da área (menu,
 *     painel, aba nova) a listagem abre limpa.
 *
 * Uso no início do index():
 *   if ($redirecionar = $this->filtrosPersistentes($request, ['busca', 'setor_id'])) {
 *       return $redirecionar;
 *   }
 * O link "Limpar" aponta para ?limpar=1.
 */
trait FiltrosPersistentes
{
    /** @param  array<int, string>  $chaves */
    protected function filtrosPersistentes(Request $request, array $chaves): ?RedirectResponse
    {
        $rota = (string) $request->route()?->getName();
        $chaveSessao = "filtros.{$rota}";
        $chaves[] = 'page';

        if ($request->has('limpar')) {
            session()->forget($chaveSessao);

            return redirect()->route($rota);
        }

        $daMesmaArea = $this->veioDaMesmaArea($request);
        $lembrados = $daMesmaArea ? session($chaveSessao, []) : [];

        if (array_filter($chaves, fn ($c) => $request->query->has($c)) !== []) {
            session()->put($chaveSessao, $this->mesclar($lembrados, $request, $chaves));

            return null;
        }

        if (! $daMesmaArea) {
            session()->forget($chaveSessao);

            return null;
        }

        // Parâmetros que não são filtro (ex.: ?editar=5 dos cadastros simples)
        // seguem junto, senão o redirecionamento os perderia.
        return $lembrados !== [] ? redirect()->route($rota, $lembrados + $request->query()) : null;
    }

    /**
     * @param  array<string, mixed>  $lembrados
     * @param  array<int, string>  $chaves
     * @return array<string, mixed>
     */
    private function mesclar(array $lembrados, Request $request, array $chaves): array
    {
        foreach ($chaves as $chave) {
            if (! $request->query->has($chave)) {
                continue;
            }

            $valor = $request->query($chave);

            if ($valor === null || $valor === '') {
                unset($lembrados[$chave]);

                continue;
            }

            $lembrados[$chave] = $valor;
        }

        return $lembrados;
    }

    private function veioDaMesmaArea(Request $request): bool
    {
        $referer = $request->headers->get('referer');

        if (! $referer) {
            return false;
        }

        $host = parse_url($referer, PHP_URL_HOST);

        if ($host !== null && $host !== $request->getHost()) {
            return false;
        }

        $de = rtrim((string) (parse_url($referer, PHP_URL_PATH) ?: '/'), '/').'/';
        $para = '/'.trim($request->path(), '/').'/';

        return str_starts_with($de, $para);
    }
}
