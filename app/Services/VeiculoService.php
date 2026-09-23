<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CondicaoVeiculo;
use App\Enums\SituacaoCondicao;
use App\Enums\SituacaoVeiculo;
use App\Models\Manutencao;
use App\Models\Veiculo;
use App\Models\VeiculoHistoricoEstado;
use Illuminate\Support\Facades\DB;

/**
 * Regras do veículo: criação com condições iniciais, mudanças de situação,
 * estado físico e km sempre com rastro em `veiculo_historico_estados`.
 * Alocação, checagem e manutenção (fases 2 e 3) passam por aqui para mudar o
 * veículo — nunca mexem nas colunas diretamente.
 */
class VeiculoService
{
    /** @param  array<string, mixed>  $dados */
    public function criar(array $dados): Veiculo
    {
        return DB::transaction(function () use ($dados): Veiculo {
            $dados['estado_atual'] = $dados['estado_inicial'];
            $dados['km_atual'] = $dados['km_inicial'] ?? 0;
            $dados['situacao'] = $dados['situacao'] ?? SituacaoVeiculo::Disponivel->value;

            $veiculo = Veiculo::create($dados);

            foreach (array_keys(config('frota.sistemas_mecanicos')) as $sistema) {
                $veiculo->condicoes()->create(['sistema' => $sistema, 'situacao' => SituacaoCondicao::Ok->value]);
            }

            $this->historico($veiculo, 'situacao', null, $veiculo->situacao->value, 'cadastro');
            $this->historico($veiculo, 'estado_atual', null, $veiculo->estado_atual->value, 'cadastro');
            $this->historico($veiculo, 'km_atual', null, (string) $veiculo->km_atual, 'cadastro');

            return $veiculo;
        });
    }

    /** @param  array<string, mixed>  $dados */
    public function atualizar(Veiculo $veiculo, array $dados): Veiculo
    {
        return DB::transaction(function () use ($veiculo, $dados): Veiculo {
            // Estado inicial nunca muda depois do cadastro.
            unset($dados['estado_inicial'], $dados['km_inicial'], $dados['situacao'], $dados['estado_atual'], $dados['km_atual']);
            $veiculo->update($dados);

            return $veiculo;
        });
    }

    public function mudarSituacao(Veiculo $veiculo, SituacaoVeiculo $nova, string $origem = 'manual', ?int $origemId = null, ?string $observacao = null): void
    {
        if ($veiculo->situacao === $nova) {
            return;
        }

        if ($origem === 'manual') {
            // Em uso, reservado e em manutenção pertencem ao fluxo de alocação
            // e manutenção: trocar à mão deixaria alocação/manutenção órfã.
            $doFluxo = [SituacaoVeiculo::EmUso, SituacaoVeiculo::Reservado, SituacaoVeiculo::EmManutencao];
            if (in_array($veiculo->situacao, $doFluxo, true)) {
                throw new \DomainException("O veículo está {$veiculo->situacao->rotulo()}. Conclua ou cancele a alocação/manutenção antes de mudar a situação à mão.");
            }
            if ($veiculo->situacao === SituacaoVeiculo::Baixado && $nova !== SituacaoVeiculo::Disponivel) {
                throw new \DomainException('Um veículo baixado só pode voltar como disponível.');
            }

            $abertas = Manutencao::where('veiculo_id', $veiculo->id)->abertas();
            if ($nova === SituacaoVeiculo::Disponivel && (clone $abertas)->where('bloqueou_veiculo', true)->exists()) {
                throw new \DomainException('Há manutenção aberta bloqueando o veículo. Conclua ou cancele a manutenção para liberá-lo.');
            }
            // Baixado some das telas e dos planos: manutenção aberta ficaria
            // esquecida, somando no "comprometido" dos relatórios.
            if ($nova === SituacaoVeiculo::Baixado && $abertas->exists()) {
                throw new \DomainException('O veículo tem manutenção aberta. Conclua ou cancele antes de baixá-lo.');
            }
        }

        DB::transaction(function () use ($veiculo, $nova, $origem, $origemId, $observacao): void {
            $anterior = $veiculo->situacao->value;
            $veiculo->update(['situacao' => $nova->value]);
            $this->historico($veiculo, 'situacao', $anterior, $nova->value, $origem, $origemId, $observacao);
        });
    }

    public function mudarEstadoFisico(Veiculo $veiculo, CondicaoVeiculo $novo, string $origem = 'manual', ?int $origemId = null, ?string $observacao = null): void
    {
        if ($veiculo->estado_atual === $novo) {
            return;
        }

        DB::transaction(function () use ($veiculo, $novo, $origem, $origemId, $observacao): void {
            $anterior = $veiculo->estado_atual->value;
            $veiculo->update(['estado_atual' => $novo->value]);
            $this->historico($veiculo, 'estado_atual', $anterior, $novo->value, $origem, $origemId, $observacao);
        });
    }

    public function atualizarKm(Veiculo $veiculo, int $km, string $origem = 'manual', ?int $origemId = null, ?string $observacao = null): void
    {
        if ($km === $veiculo->km_atual) {
            return;
        }

        if ($origem === 'manual') {
            // Com o carro na rua, o retorno exige km >= o da saída: mexer
            // agora poderia impedir o motorista de devolver.
            if ($veiculo->situacao === SituacaoVeiculo::EmUso) {
                throw new \DomainException('O veículo está em uso. Ajuste a quilometragem depois da devolução.');
            }
            // A correção manual pode baixar o km: é assim que se desfaz um
            // dígito a mais digitado numa checagem. O motivo fica no histórico.
        } elseif ($km < $veiculo->km_atual) {
            throw new \DomainException("A quilometragem informada ({$km}) é menor que a atual ({$veiculo->km_atual}).");
        }

        DB::transaction(function () use ($veiculo, $km, $origem, $origemId, $observacao): void {
            $anterior = (string) $veiculo->km_atual;
            $veiculo->update(['km_atual' => $km]);
            $this->historico($veiculo, 'km_atual', $anterior, (string) $km, $origem, $origemId, $observacao);
        });
    }

    /**
     * Atualiza as condições mecânicas em lote (tela do veículo).
     *
     * @param  array<string, array{situacao: string, observacao?: string|null}>  $condicoes  sistema => dados
     * @return int quantidade alterada
     */
    public function atualizarCondicoes(Veiculo $veiculo, array $condicoes, int $usuarioId): int
    {
        return DB::transaction(function () use ($veiculo, $condicoes, $usuarioId): int {
            $alteradas = 0;

            foreach ($veiculo->condicoes as $condicao) {
                $novo = $condicoes[$condicao->sistema] ?? null;
                if ($novo === null) {
                    continue;
                }

                $situacao = SituacaoCondicao::from($novo['situacao']);
                $observacao = trim((string) ($novo['observacao'] ?? '')) ?: null;

                if ($condicao->situacao === $situacao && $condicao->observacao === $observacao) {
                    continue;
                }

                $condicao->update(['situacao' => $situacao->value, 'observacao' => $observacao, 'atualizado_por_id' => $usuarioId]);
                $alteradas++;
            }

            return $alteradas;
        });
    }

    private function historico(Veiculo $veiculo, string $campo, ?string $anterior, string $novo, string $origem, ?int $origemId = null, ?string $observacao = null): void
    {
        VeiculoHistoricoEstado::create([
            'veiculo_id' => $veiculo->id,
            'campo' => $campo,
            'valor_anterior' => $anterior,
            'valor_novo' => $novo,
            'origem' => $origem,
            'origem_id' => $origemId,
            'observacao' => $observacao !== null ? mb_substr($observacao, 0, 255) : null,
            'usuario_id' => auth()->id(),
        ]);
    }
}
