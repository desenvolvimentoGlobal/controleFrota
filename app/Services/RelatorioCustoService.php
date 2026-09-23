<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SituacaoAlocacao;
use App\Enums\SituacaoManutencao;
use App\Enums\TipoManutencao;
use App\Models\Alocacao;
use App\Models\Fornecedor;
use App\Models\Manutencao;
use App\Models\Veiculo;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Fonte ÚNICA do relatório de custos de manutenção (tela, PDF e Excel).
 *
 * Regra do período: conta a manutenção PRESTADA cuja conclusão caiu no
 * intervalo, pelo preço final. É o custo realizado. O que ainda está aberto
 * aparece à parte como "comprometido" (preço previsto), sem entrar no total.
 *
 * Custo por km: custo do veículo no período ÷ km rodados nas alocações
 * concluídas no mesmo período. Sem km rodado, não há custo por km.
 */
class RelatorioCustoService
{
    public const VISOES = [
        'veiculo' => 'Por veículo',
        'fornecedor' => 'Por fornecedor',
        'tipo' => 'Por tipo',
        'mes' => 'Por mês',
    ];

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function gerar(array $filtros): array
    {
        $f = $this->normalizar($filtros);

        /** @var Collection<int, Manutencao> $manutencoes */
        $manutencoes = Manutencao::with(['veiculo:id,nome,placa', 'fornecedor:id,razao_social,nome_fantasia'])
            ->where('situacao', SituacaoManutencao::Prestada->value)
            ->whereBetween('concluida_em', [$f['de']->startOfDay(), $f['ate']->endOfDay()])
            ->when($f['veiculo_id'], fn ($q, $id) => $q->where('veiculo_id', $id))
            ->when($f['fornecedor_id'], fn ($q, $id) => $q->where('fornecedor_id', $id))
            ->when($f['tipo'], fn ($q, $t) => $q->where('tipo', $t))
            ->orderBy('concluida_em')
            ->get();

        $kmPorVeiculo = $this->kmRodados($f);

        $total = (float) $manutencoes->sum(fn (Manutencao $m) => (float) $m->preco_final);
        $previstoDasRealizadas = (float) $manutencoes->sum(fn (Manutencao $m) => (float) ($m->preco_previsto ?? $m->preco_final));
        $kmTotal = $f['veiculo_id'] ? (int) ($kmPorVeiculo[$f['veiculo_id']] ?? 0) : (int) array_sum($kmPorVeiculo);

        $comprometido = Manutencao::abertas()
            ->when($f['veiculo_id'], fn ($q, $id) => $q->where('veiculo_id', $id))
            ->when($f['fornecedor_id'], fn ($q, $id) => $q->where('fornecedor_id', $id))
            ->when($f['tipo'], fn ($q, $t) => $q->where('tipo', $t))
            ->selectRaw('count(*) as quantidade, sum(coalesce(preco_previsto, 0)) as valor')
            ->first();

        return [
            'filtros' => $f,
            'filtrosQuery' => $this->paraQuery($f),
            'periodoLabel' => $f['de']->format('d/m/Y').' a '.$f['ate']->format('d/m/Y'),
            'visao' => $f['visao'],
            'visaoLabel' => self::VISOES[$f['visao']],
            'filtrosLabel' => $this->descreverFiltros($f),
            'resumo' => [
                'total' => $total,
                'quantidade' => $manutencoes->count(),
                'media' => $manutencoes->isEmpty() ? 0.0 : $total / $manutencoes->count(),
                'previsto' => $previstoDasRealizadas,
                'variacao' => $previstoDasRealizadas > 0 ? ($total - $previstoDasRealizadas) / $previstoDasRealizadas * 100 : null,
                'km' => $kmTotal,
                'custo_km' => $kmTotal > 0 ? $total / $kmTotal : null,
                'comprometido' => (float) ($comprometido->valor ?? 0),
                'comprometido_qtd' => (int) ($comprometido->quantidade ?? 0),
            ],
            'linhas' => $this->agrupar($manutencoes, $f['visao'], $kmPorVeiculo, $total),
            'mensal' => $this->serieMensal($manutencoes, $f),
            'manutencoes' => $manutencoes,
        ];
    }

    /**
     * Custo mensal realizado dos últimos N meses (painel).
     *
     * @return array{rotulos: array<int, string>, valores: array<int, float>}
     */
    public function ultimosMeses(int $meses = 6): array
    {
        $inicio = CarbonImmutable::now()->startOfMonth()->subMonths($meses - 1);

        $porMes = Manutencao::where('situacao', SituacaoManutencao::Prestada->value)
            ->where('concluida_em', '>=', $inicio)
            ->get(['concluida_em', 'preco_final'])
            ->groupBy(fn (Manutencao $m) => $m->concluida_em->format('Y-m'))
            ->map(fn (Collection $g) => (float) $g->sum(fn ($m) => (float) $m->preco_final));

        $rotulos = [];
        $valores = [];
        for ($i = 0; $i < $meses; $i++) {
            $mes = $inicio->addMonths($i);
            $rotulos[] = $mes->translatedFormat('M/y');
            $valores[] = round($porMes[$mes->format('Y-m')] ?? 0, 2);
        }

        return ['rotulos' => $rotulos, 'valores' => $valores];
    }

    // ─── Apoio ─────────────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{de: CarbonImmutable, ate: CarbonImmutable, veiculo_id: ?int, fornecedor_id: ?int, tipo: ?string, visao: string}
     */
    private function normalizar(array $filtros): array
    {
        $de = $this->data($filtros['de'] ?? null) ?? CarbonImmutable::now()->startOfYear();
        $ate = $this->data($filtros['ate'] ?? null) ?? CarbonImmutable::now();
        if ($ate->lt($de)) {
            [$de, $ate] = [$ate, $de];
        }

        $tipo = $filtros['tipo'] ?? null;
        $visao = $filtros['visao'] ?? 'veiculo';

        return [
            'de' => $de,
            'ate' => $ate,
            'veiculo_id' => ! empty($filtros['veiculo_id']) ? (int) $filtros['veiculo_id'] : null,
            'fornecedor_id' => ! empty($filtros['fornecedor_id']) ? (int) $filtros['fornecedor_id'] : null,
            'tipo' => in_array($tipo, TipoManutencao::valores(), true) ? $tipo : null,
            'visao' => array_key_exists($visao, self::VISOES) ? $visao : 'veiculo',
        ];
    }

    private function data(mixed $valor): ?CarbonImmutable
    {
        if (! is_string($valor) || $valor === '') {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('Y-m-d', $valor)?->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Km rodados por veículo nas alocações concluídas no período.
     *
     * @param  array<string, mixed>  $f
     * @return array<int, int> veiculo_id => km
     */
    private function kmRodados(array $f): array
    {
        return Alocacao::where('situacao', SituacaoAlocacao::Concluida->value)
            ->whereBetween('retorno_real', [$f['de']->startOfDay(), $f['ate']->endOfDay()])
            ->whereNotNull('km_saida')->whereNotNull('km_retorno')
            ->when($f['veiculo_id'], fn ($q, $id) => $q->where('veiculo_id', $id))
            ->get(['veiculo_id', 'km_saida', 'km_retorno'])
            ->groupBy('veiculo_id')
            ->map(fn (Collection $g) => (int) $g->sum(fn (Alocacao $a) => max(0, $a->km_retorno - $a->km_saida)))
            ->all();
    }

    /**
     * @param  Collection<int, Manutencao>  $manutencoes
     * @param  array<int, int>  $kmPorVeiculo
     * @return array<int, array<string, mixed>>
     */
    private function agrupar(Collection $manutencoes, string $visao, array $kmPorVeiculo, float $total): array
    {
        $grupos = $manutencoes->groupBy(fn (Manutencao $m) => match ($visao) {
            'veiculo' => (string) $m->veiculo_id,
            'fornecedor' => (string) ($m->fornecedor_id ?? 0),
            'tipo' => $m->tipo->value,
            'mes' => $m->concluida_em->format('Y-m'),
        });

        // Chave int|string: o PHP converte '12' em 12 nas chaves de array.
        $linhas = $grupos->map(function (Collection $grupo, int|string $chave) use ($visao, $kmPorVeiculo, $total): array {
            /** @var Manutencao $primeira */
            $primeira = $grupo->first();
            $custo = (float) $grupo->sum(fn (Manutencao $m) => (float) $m->preco_final);
            $km = $visao === 'veiculo' ? (int) ($kmPorVeiculo[(int) $chave] ?? 0) : null;

            return [
                'chave' => (string) $chave,
                'rotulo' => match ($visao) {
                    'veiculo' => $primeira->veiculo->nome,
                    'fornecedor' => $primeira->fornecedor?->nome ?? 'Sem fornecedor',
                    'tipo' => $primeira->tipo->rotulo(),
                    'mes' => $primeira->concluida_em->translatedFormat('F \d\e Y'),
                },
                'detalhe' => $visao === 'veiculo' ? $primeira->veiculo->placa : null,
                'quantidade' => $grupo->count(),
                'custo' => $custo,
                'media' => $custo / max(1, $grupo->count()),
                'percentual' => $total > 0 ? $custo / $total * 100 : 0.0,
                'km' => $km,
                'custo_km' => $km ? $custo / $km : null,
            ];
        });

        // Mês em ordem cronológica; as demais do maior custo para o menor.
        return ($visao === 'mes' ? $linhas->sortKeys() : $linhas->sortByDesc('custo'))->values()->all();
    }

    /**
     * @param  Collection<int, Manutencao>  $manutencoes
     * @param  array<string, mixed>  $f
     * @return array{rotulos: array<int, string>, valores: array<int, float>, truncado: bool}
     */
    private function serieMensal(Collection $manutencoes, array $f): array
    {
        $porMes = $manutencoes->groupBy(fn (Manutencao $m) => $m->concluida_em->format('Y-m'))
            ->map(fn (Collection $g) => (float) $g->sum(fn ($m) => (float) $m->preco_final));

        $rotulos = [];
        $valores = [];
        // Período longo: o gráfico mostra os ÚLTIMOS 36 meses (o total e as
        // tabelas continuam cobrindo tudo) e a tela avisa o corte.
        $maximo = 36;
        $primeiro = $f['de']->startOfMonth();
        $ultimo = $f['ate']->startOfMonth();
        $truncado = $primeiro->diffInMonths($ultimo) + 1 > $maximo;
        $mes = $truncado ? $ultimo->subMonths($maximo - 1) : $primeiro;

        while ($mes->lte($ultimo)) {
            $rotulos[] = $mes->translatedFormat('M/y');
            $valores[] = round($porMes[$mes->format('Y-m')] ?? 0, 2);
            $mes = $mes->addMonth();
        }

        return ['rotulos' => $rotulos, 'valores' => $valores, 'truncado' => $truncado];
    }

    /**
     * @param  array<string, mixed>  $f
     * @return array<string, string>
     */
    private function paraQuery(array $f): array
    {
        return array_filter([
            'de' => $f['de']->toDateString(),
            'ate' => $f['ate']->toDateString(),
            'veiculo_id' => $f['veiculo_id'] ? (string) $f['veiculo_id'] : null,
            'fornecedor_id' => $f['fornecedor_id'] ? (string) $f['fornecedor_id'] : null,
            'tipo' => $f['tipo'],
            'visao' => $f['visao'],
        ]);
    }

    /** @param  array<string, mixed>  $f */
    private function descreverFiltros(array $f): string
    {
        $partes = array_filter([
            $f['veiculo_id'] ? 'Veículo: '.(Veiculo::withTrashed()->find($f['veiculo_id'])?->nome ?? '#'.$f['veiculo_id']) : null,
            $f['fornecedor_id'] ? 'Fornecedor: '.(Fornecedor::withTrashed()->find($f['fornecedor_id'])?->nome ?? '#'.$f['fornecedor_id']) : null,
            $f['tipo'] ? 'Tipo: '.TipoManutencao::rotuloDe($f['tipo']) : null,
        ]);

        return $partes === [] ? 'Todos os veículos, fornecedores e tipos' : implode(' · ', $partes);
    }
}
