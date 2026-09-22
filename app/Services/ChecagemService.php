<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CondicaoVeiculo;
use App\Enums\SituacaoAlocacao;
use App\Enums\SituacaoItemChecagem;
use App\Enums\SituacaoOcorrencia;
use App\Enums\SituacaoVeiculo;
use App\Enums\TipoChecagem;
use App\Models\Alocacao;
use App\Models\Checagem;
use App\Models\ChecagemFoto;
use App\Models\ChecagemItem;
use App\Models\Ocorrencia;
use App\Models\Usuario;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Checagem por fotos (docs/PLANEJAMENTO.md 3.3). O motorista compara cada
 * item com a última foto do veículo e marca conforme/anomalia. Ao concluir:
 *  - saída: alocação vira em_uso, veículo em_uso, km e estado atualizados;
 *  - retorno: alocação concluída, veículo disponível, km e estado atualizados;
 *  - cada anomalia vira uma ocorrência contra a alocação anterior.
 */
class ChecagemService
{
    public const DISCO = 'local';

    public function __construct(
        private readonly VeiculoService $veiculos,
        private readonly AlocacaoService $alocacoes,
        private readonly NotificacaoService $notificar,
    ) {}

    /** Devolve o rascunho da checagem devida (cria se não existir). */
    public function obterOuIniciar(Alocacao $alocacao, Usuario $motorista): Checagem
    {
        $tipo = $alocacao->proximaChecagem();
        if ($tipo === null) {
            throw new \DomainException('Esta alocação não tem checagem pendente.');
        }
        if ($alocacao->motorista_id !== $motorista->id) {
            throw new \DomainException('Só o motorista da alocação faz a checagem.');
        }

        $existente = $alocacao->checagens()->where('tipo', $tipo->value)->first();
        if ($existente) {
            return $existente;
        }

        return DB::transaction(function () use ($alocacao, $motorista, $tipo): Checagem {
            $anterior = $this->ultimaChecagemConcluida($alocacao->veiculo_id);

            $checagem = Checagem::create([
                'alocacao_id' => $alocacao->id,
                'veiculo_id' => $alocacao->veiculo_id,
                'motorista_id' => $motorista->id,
                'tipo' => $tipo->value,
                'checagem_anterior_id' => $anterior?->id,
            ]);

            foreach (config('frota.checagem.categorias') as $categoria => $def) {
                foreach (array_keys($def['itens']) as $item) {
                    $checagem->itens()->create(['categoria' => $categoria, 'item' => $item]);
                }
            }

            return $checagem;
        });
    }

    /** Foto + resposta de um item. Substitui a foto anterior do mesmo item no rascunho. */
    public function registrarItem(Checagem $checagem, ChecagemItem $item, UploadedFile $foto, SituacaoItemChecagem $situacao, ?string $observacao, Usuario $quem): ChecagemItem
    {
        $this->garantirRascunho($checagem, $quem);

        if ($situacao === SituacaoItemChecagem::Anomalia && blank($observacao)) {
            throw new \DomainException('Descreva a anomalia encontrada.');
        }

        return DB::transaction(function () use ($checagem, $item, $foto, $situacao, $observacao): ChecagemItem {
            foreach ($item->fotos as $antiga) {
                Storage::disk(self::DISCO)->delete($antiga->caminho);
                $antiga->delete();
            }

            $caminho = $foto->storeAs(
                "checagens/{$checagem->id}",
                $item->item.'-'.Str::uuid().'.'.($foto->extension() ?: 'jpg'),
                self::DISCO,
            );

            $item->fotos()->create([
                'caminho' => $caminho,
                'nome_original' => mb_substr((string) $foto->getClientOriginalName(), 0, 255),
                'mime' => $foto->getMimeType(),
                'tamanho' => $foto->getSize(),
                'enviada_por_id' => auth()->id(),
            ]);

            $item->update(['situacao' => $situacao->value, 'observacao' => $observacao ? mb_substr($observacao, 0, 500) : null]);

            return $item->fresh(['fotos']);
        });
    }

    /**
     * Conclui a checagem e aplica o efeito na alocação e no veículo.
     *
     * @param  array{km_informado: int, nivel_combustivel: string, estado_geral: string, observacao_motorista?: string|null}  $dados
     */
    public function concluir(Checagem $checagem, array $dados, Usuario $quem): Checagem
    {
        $this->garantirRascunho($checagem, $quem);
        $checagem->load(['itens.fotos', 'alocacao.veiculo', 'anterior.itens.fotoAtual', 'anterior.alocacao']);

        if ($checagem->itensPendentes() > 0) {
            throw new \DomainException("Ainda faltam {$checagem->itensPendentes()} item(ns) com foto e resposta.");
        }

        $alocacao = $checagem->alocacao;
        $veiculo = $alocacao->veiculo;
        $km = (int) $dados['km_informado'];
        $estado = CondicaoVeiculo::from($dados['estado_geral']);

        $kmMinimo = $checagem->tipo === TipoChecagem::Retorno ? ($alocacao->km_saida ?? $veiculo->km_atual) : $veiculo->km_atual;
        if ($km < $kmMinimo) {
            throw new \DomainException("A quilometragem informada ({$km}) é menor que a registrada ({$kmMinimo}).");
        }

        return DB::transaction(function () use ($checagem, $alocacao, $veiculo, $km, $estado, $dados, $quem): Checagem {
            $checagem->update([
                'km_informado' => $km,
                'nivel_combustivel' => $dados['nivel_combustivel'],
                'estado_geral' => $estado->value,
                'observacao_motorista' => $dados['observacao_motorista'] ?? null,
                'situacao' => 'concluida',
                'concluida_em' => now(),
            ]);

            $origemId = $checagem->id;

            if ($checagem->tipo === TipoChecagem::Saida) {
                $alocacao->update([
                    'situacao' => SituacaoAlocacao::EmUso->value,
                    'saida_real' => now(),
                    'km_saida' => $km,
                    'estado_saida' => $estado->value,
                ]);
                $this->veiculos->mudarSituacao($veiculo, SituacaoVeiculo::EmUso, 'checagem', $origemId, "Saída da alocação #{$alocacao->id}");
            } else {
                $alocacao->update([
                    'situacao' => SituacaoAlocacao::Concluida->value,
                    'retorno_real' => now(),
                    'km_retorno' => $km,
                    'estado_retorno' => $estado->value,
                ]);
                $this->veiculos->mudarSituacao($veiculo, SituacaoVeiculo::Disponivel, 'checagem', $origemId, "Retorno da alocação #{$alocacao->id}");
            }

            $this->veiculos->atualizarKm($veiculo, $km, 'checagem', $origemId, "Checagem de {$checagem->tipo->rotulo()}");
            $this->veiculos->mudarEstadoFisico($veiculo, $estado, 'checagem', $origemId, "Checagem de {$checagem->tipo->rotulo()}");

            $this->abrirOcorrencias($checagem, $quem);

            return $checagem->fresh();
        });
    }

    /** Última checagem concluída do veículo: a referência de comparação. */
    public function ultimaChecagemConcluida(int $veiculoId, ?int $ignorarId = null): ?Checagem
    {
        return Checagem::where('veiculo_id', $veiculoId)
            ->concluidas()
            ->when($ignorarId, fn ($q) => $q->whereKeyNot($ignorarId))
            ->orderByDesc('concluida_em')
            ->orderByDesc('id')
            ->first();
    }

    /** Scheduler: apaga o ARQUIVO das fotos antigas; o registro fica. */
    public function apagarFotosAntigas(): int
    {
        $limite = now()->subMonths((int) config('frota.checagem.retencao_meses', 6));

        $fotos = ChecagemFoto::whereNull('apagada_em')
            ->whereHas('item.checagem', fn ($q) => $q->concluidas()->where('concluida_em', '<', $limite))
            // Preserva evidência de ocorrência ainda relevante.
            ->whereDoesntHave('item.ocorrencia', fn ($q) => $q->whereIn('situacao', [SituacaoOcorrencia::Aberta->value, SituacaoOcorrencia::Confirmada->value]))
            ->whereDoesntHave('item', fn ($q) => $q->whereHas('ocorrenciaComoAnterior', fn ($o) => $o->whereIn('situacao', [SituacaoOcorrencia::Aberta->value, SituacaoOcorrencia::Confirmada->value])))
            ->get();

        foreach ($fotos as $foto) {
            Storage::disk(self::DISCO)->delete($foto->caminho);
            $foto->update(['apagada_em' => now()]);
        }

        return $fotos->count();
    }

    // ─── Apoio ─────────────────────────────────────────────────────────────────

    private function abrirOcorrencias(Checagem $checagem, Usuario $quem): void
    {
        $anterior = $checagem->anterior;
        $itensAnteriores = $anterior?->itens->keyBy('item') ?? collect();
        $responsavelAlocacao = $anterior?->alocacao;

        $abertas = [];

        foreach ($checagem->itens->where('situacao', SituacaoItemChecagem::Anomalia) as $item) {
            $abertas[] = Ocorrencia::create([
                'veiculo_id' => $checagem->veiculo_id,
                'checagem_item_id' => $item->id,
                'checagem_item_anterior_id' => $itensAnteriores[$item->item]->id ?? null,
                'alocacao_responsavel_id' => $responsavelAlocacao?->id,
                'apontada_por_id' => $quem->id,
                'descricao' => (string) $item->observacao,
            ]);
        }

        if ($abertas === []) {
            return;
        }

        $veiculo = $checagem->alocacao->veiculo;
        $responsavel = $responsavelAlocacao?->motorista;
        $quantas = count($abertas);
        $url = route('ocorrencias.index', ['veiculo_id' => $veiculo->id], false);

        $revisores = $this->notificar->responsaveisPor($responsavel ?? $quem, $quem->id);
        $this->notificar->enviar($revisores, 'ocorrencia_aberta', "{$quantas} ocorrência(s) no veículo {$veiculo->nome}",
            "{$quem->nome} apontou {$quantas} anomalia(s) na checagem de {$checagem->tipo->rotulo()}".($responsavel ? ", com responsável presumido {$responsavel->nome}." : '.'),
            $url);

        if ($responsavel && $responsavel->id !== $quem->id) {
            $this->notificar->enviar([$responsavel], 'ocorrencia_contra_voce', "Ocorrência registrada na sua última alocação do {$veiculo->nome}",
                "{$quem->nome} apontou {$quantas} anomalia(s) comparando com as suas fotos. Você pode contestar.",
                $url);
        }
    }

    private function garantirRascunho(Checagem $checagem, Usuario $quem): void
    {
        if ($checagem->concluida()) {
            throw new \DomainException('Esta checagem já foi concluída.');
        }
        if ($checagem->motorista_id !== $quem->id) {
            throw new \DomainException('Só o motorista da alocação faz a checagem.');
        }
    }
}
