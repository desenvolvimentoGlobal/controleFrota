<?php

declare(strict_types=1);

namespace App\Services\Integracao;

use App\Models\Alocacao;
use App\Models\Veiculo;

/**
 * Contrato JSON da API de integração. Serialização EXPLÍCITA (nunca
 * toArray()): o que sai para outro sistema é decidido aqui, campo a campo.
 * Valor de aquisição, RENAVAM, chassi e seguradora NÃO saem.
 * Documentação: docs/API.md.
 */
final class VeiculoParaIntegracao
{
    /** @return array<string, mixed> */
    public static function veiculo(Veiculo $v): array
    {
        return [
            'id' => $v->id,
            'nome' => $v->nome,
            'placa' => $v->placa,
            'placa_formatada' => $v->placa_formatada,
            'descricao' => $v->descricao,
            'marca' => $v->marca,
            'modelo' => $v->modelo,
            'versao' => $v->versao,
            'ano_modelo' => $v->ano_modelo,
            'cor' => $v->cor,
            'carroceria' => $v->carroceria,
            'lugares' => $v->lugares,
            'situacao' => $v->situacao->value,
            'situacao_rotulo' => $v->situacao->rotulo(),
            'estado_atual' => $v->estado_atual->value,
            'km_atual' => $v->km_atual,
            'sistema_critico' => $v->temCondicaoCritica(),
            'atualizado_em' => $v->updated_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public static function alocacao(Alocacao $a): array
    {
        return [
            'id' => $a->id,
            'veiculo_id' => $a->veiculo_id,
            'placa' => $a->veiculo->placa,
            'motorista' => $a->motorista->nome,
            'objetivo' => $a->objetivo,
            'destino' => $a->destino,
            'saida_prevista' => $a->saida_prevista->toIso8601String(),
            'retorno_previsto' => $a->retorno_previsto->toIso8601String(),
            'saida_real' => $a->saida_real?->toIso8601String(),
            'retorno_real' => $a->retorno_real?->toIso8601String(),
            'situacao' => $a->situacao->value,
            'situacao_rotulo' => $a->situacao->rotulo(),
        ];
    }
}
