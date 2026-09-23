<?php

declare(strict_types=1);

namespace App\Integracoes;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente da API do gestaoPessoas (`/api/integracao/v1`), no molde do
 * emissaoOS: timeout curto e FALHA SILENCIOSA — a integração fora do ar
 * nunca derruba uma tela daqui. `null` = falhou (diferente de lista vazia).
 *
 * Configuração (.env / .env.docker):
 *   GESTAO_PESSOAS_URL=https://pessoas.exemplo
 *   GESTAO_PESSOAS_TOKEN=...   (emitido LÁ: php artisan integracao:token criar controle-frota)
 */
class GestaoPessoas
{
    private const CACHE_CHAVE = 'integracao.pessoas.colaboradores';

    private const CACHE_SEGUNDOS = 300;

    public function configurada(): bool
    {
        return (string) config('services.gestao_pessoas.url') !== '' && (string) config('services.gestao_pessoas.token') !== '';
    }

    /**
     * Colaboradores (id, nome, setor, cargo, telefone, situacao...).
     *
     * @return array<int, array<string, mixed>>|null
     */
    public function colaboradores(string $situacao = 'todos'): ?array
    {
        return $this->lista('colaboradores', ['situacao' => $situacao]);
    }

    /**
     * Mesma lista, guardada por 5 minutos (para montar o select da tela).
     * Falha não é guardada: a próxima tentativa consulta de novo.
     *
     * @return array<int, array<string, mixed>>|null
     */
    public function colaboradoresEmCache(): ?array
    {
        if (! $this->configurada()) {
            return null;
        }

        $guardado = Cache::get(self::CACHE_CHAVE);
        if (is_array($guardado)) {
            return $guardado;
        }

        $lista = $this->colaboradores();
        if ($lista !== null) {
            Cache::put(self::CACHE_CHAVE, $lista, self::CACHE_SEGUNDOS);
        }

        return $lista;
    }

    /**
     * @param  array<string, mixed>  $parametros
     * @return array<int, array<string, mixed>>|null
     */
    private function lista(string $recurso, array $parametros = []): ?array
    {
        if (! $this->configurada()) {
            return null;
        }

        try {
            $resposta = Http::baseUrl(rtrim((string) config('services.gestao_pessoas.url'), '/').'/api/integracao/v1')
                ->withToken((string) config('services.gestao_pessoas.token'))
                ->acceptJson()
                ->timeout(5)
                ->connectTimeout(3)
                ->get($recurso, $parametros);

            if (! $resposta->ok() || $resposta->json('sucesso') !== true) {
                Log::warning('gestaoPessoas respondeu com erro', ['recurso' => $recurso, 'status' => $resposta->status()]);

                return null;
            }

            $dados = $resposta->json('dados');

            return is_array($dados) ? array_values($dados) : null;
        } catch (\Throwable $e) {
            Log::warning('gestaoPessoas indisponível', ['recurso' => $recurso, 'motivo' => $e->getMessage()]);

            return null;
        }
    }
}
