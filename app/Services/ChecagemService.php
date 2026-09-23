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
use App\Models\Veiculo;
use Illuminate\Database\UniqueConstraintViolationException;
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

        if ($tipo === TipoChecagem::Saida) {
            $this->garantirVeiculoLiberadoParaSair($alocacao);
        }

        $existente = $alocacao->checagens()->where('tipo', $tipo->value)->first();
        if ($existente) {
            // O rascunho pode ter sido aberto antes de outra checagem do
            // veículo ser concluída: a comparação é sempre com a mais recente.
            $ultima = $this->ultimaChecagemConcluida($alocacao->veiculo_id, $existente->id);
            if (! $existente->concluida() && $existente->checagem_anterior_id !== $ultima?->id) {
                $existente->update(['checagem_anterior_id' => $ultima?->id]);
            }

            return $existente;
        }

        try {
            return $this->criarRascunho($alocacao, $motorista, $tipo);
        } catch (UniqueConstraintViolationException) {
            // Duplo toque em "Fazer checagem": a outra requisição criou o
            // rascunho (unique alocacao_id + tipo) entre a busca e o insert.
            return $alocacao->checagens()->where('tipo', $tipo->value)->firstOrFail();
        }
    }

    private function criarRascunho(Alocacao $alocacao, Usuario $motorista, TipoChecagem $tipo): Checagem
    {
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

    /**
     * Foto + resposta de um item. A foto nova substitui a anterior do mesmo
     * item no rascunho; sem foto nova, só a resposta muda (exige que o item
     * já tenha foto).
     */
    public function registrarItem(Checagem $checagem, ChecagemItem $item, ?UploadedFile $foto, SituacaoItemChecagem $situacao, ?string $observacao, Usuario $quem): ChecagemItem
    {
        $this->garantirRascunho($checagem, $quem);

        if ($item->checagem_id !== $checagem->id) {
            throw new \DomainException('Item de outra checagem.');
        }
        if ($situacao === SituacaoItemChecagem::Anomalia && blank($observacao)) {
            throw new \DomainException('Descreva a anomalia encontrada.');
        }
        if ($foto === null && ! $item->fotos()->exists()) {
            throw new \DomainException('Tire a foto do item antes de responder.');
        }

        return DB::transaction(function () use ($checagem, $item, $foto, $situacao, $observacao): ChecagemItem {
            if ($foto !== null) {
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
            }

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

        // A referência de comparação (e o responsável presumido) é a última
        // checagem concluída do veículo NO MOMENTO da conclusão.
        $ultima = $this->ultimaChecagemConcluida($checagem->veiculo_id, $checagem->id);
        if ($checagem->checagem_anterior_id !== $ultima?->id) {
            $checagem->update(['checagem_anterior_id' => $ultima?->id]);
        }

        $checagem->load(['itens.fotos', 'alocacao.veiculo', 'anterior.itens.fotoAtual', 'anterior.alocacao']);

        if ($checagem->itensPendentes() > 0) {
            throw new \DomainException("Ainda faltam {$checagem->itensPendentes()} item(ns) com foto e resposta.");
        }

        $alocacao = $checagem->alocacao;
        $km = (int) $dados['km_informado'];
        $estado = CondicaoVeiculo::from($dados['estado_geral']);

        return DB::transaction(function () use ($checagem, $alocacao, $km, $estado, $dados, $quem): Checagem {
            // Duplo toque em "Concluir" no celular: a segunda requisição espera
            // a primeira e encontra a checagem já concluída.
            $travada = Checagem::whereKey($checagem->id)->lockForUpdate()->firstOrFail();
            if ($travada->concluida()) {
                throw new \DomainException('Esta checagem já foi concluída.');
            }

            // Trava o veículo: duas saídas simultâneas de alocações diferentes
            // do mesmo carro não podem passar juntas pela verificação abaixo.
            $veiculo = Veiculo::with('condicoes')->whereKey($alocacao->veiculo_id)->lockForUpdate()->firstOrFail();
            $alocacao->setRelation('veiculo', $veiculo);

            // O veículo pode ter ido para a oficina entre abrir e concluir o rascunho.
            if ($checagem->tipo === TipoChecagem::Saida) {
                $this->garantirVeiculoLiberadoParaSair($alocacao, $veiculo);
            }

            $this->validarKm($checagem, $alocacao, $veiculo, $km);

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
                // Volta a reservado se já houver outra alocação saindo hoje.
                if ($veiculo->situacao === SituacaoVeiculo::EmUso) {
                    $destino = $this->alocacoes->situacaoLivre($veiculo, $alocacao->id);
                    $this->veiculos->mudarSituacao($veiculo, $destino, 'checagem', $origemId, "Retorno da alocação #{$alocacao->id}");
                }
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

        // A última checagem de cada veículo é a foto de comparação do próximo
        // motorista: um carro parado há mais de 6 meses não pode perdê-la.
        $referencias = Checagem::concluidas()->distinct()->pluck('veiculo_id')
            ->map(fn (int $veiculoId) => $this->ultimaChecagemConcluida($veiculoId)?->id)
            ->filter()->values()->all();

        $fotos = ChecagemFoto::whereNull('apagada_em')
            ->whereHas('item.checagem', fn ($q) => $q->concluidas()->where('concluida_em', '<', $limite)->whereKeyNot($referencias))
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

        // Primeira checagem do veículo: é a referência inicial e não gera
        // ocorrência (docs/PLANEJAMENTO.md 3.3). As anomalias ficam
        // registradas no item, com foto e descrição.
        if ($anterior === null) {
            return;
        }

        $itensAnteriores = $anterior->itens->keyBy('item');
        $responsavelAlocacao = $anterior->alocacao;

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

    /**
     * Só sai veículo disponível ou reservado (para esta alocação) e sem
     * sistema crítico. Em uso aqui significa que outra pessoa ainda não
     * devolveu — sair agora poria o carro "em uso" duas vezes.
     */
    private function garantirVeiculoLiberadoParaSair(Alocacao $alocacao, ?Veiculo $veiculo = null): void
    {
        $veiculo ??= $alocacao->veiculo()->with('condicoes')->firstOrFail();

        if (! in_array($veiculo->situacao, [SituacaoVeiculo::Disponivel, SituacaoVeiculo::Reservado], true)) {
            $motivo = $veiculo->situacao === SituacaoVeiculo::EmUso
                ? 'O veículo ainda não foi devolvido pela alocação anterior.'
                : "O veículo está {$veiculo->situacao->rotulo()}.";

            throw new \DomainException("{$motivo} Não é possível fazer a checagem de saída agora.");
        }

        if ($veiculo->temCondicaoCritica()) {
            throw new \DomainException('O veículo tem sistema mecânico em estado crítico e não pode sair.');
        }

        // Janela: o conflito de agenda foi validado para o período pedido.
        // Sair dias antes (ou depois do retorno previsto) usaria o carro fora dele.
        if (now()->lt($alocacao->saida_prevista->copy()->startOfDay())) {
            throw new \DomainException('A saída está prevista para '.$alocacao->saida_prevista->format('d/m/Y').'. A checagem de saída só pode ser feita a partir desse dia.');
        }
        if (now()->gte($alocacao->retorno_previsto)) {
            throw new \DomainException('O período desta alocação já terminou. Faça uma nova solicitação.');
        }

        // Sair antes do horário previsto só se ninguém tiver o carro nesse
        // intervalo: "reservado" pode ser de outra alocação do mesmo dia.
        if (now()->lt($alocacao->saida_prevista)) {
            $antes = Alocacao::conflitantes($alocacao->veiculo_id, now(), $alocacao->saida_prevista, $alocacao->id)
                ->with('motorista:id,nome')->orderBy('saida_prevista')->first();
            if ($antes) {
                throw new \DomainException(
                    "O veículo está reservado para {$antes->motorista->nome} até {$antes->retorno_previsto->format('d/m H:i')}. "
                    ."A sua saída pode ser feita a partir de {$alocacao->saida_prevista->format('d/m H:i')}."
                );
            }
        }
    }

    /**
     * Km informado na checagem. Na saída, o carro estava parado desde a última
     * devolução: um salto grande é quase sempre dígito a mais, e gravá-lo
     * travaria todas as checagens seguintes (o km não volta pela checagem).
     */
    private function validarKm(Checagem $checagem, Alocacao $alocacao, Veiculo $veiculo, int $km): void
    {
        $kmMinimo = $checagem->tipo === TipoChecagem::Retorno ? (int) ($alocacao->km_saida ?? $veiculo->km_atual) : $veiculo->km_atual;
        if ($km < $kmMinimo) {
            throw new \DomainException("A quilometragem informada ({$km}) é menor que a registrada ({$kmMinimo}).");
        }

        $tolerancia = (int) config('frota.checagem.km_tolerancia_saida', 500);
        if ($checagem->tipo === TipoChecagem::Saida && $km - $veiculo->km_atual > $tolerancia) {
            throw new \DomainException(
                "A quilometragem informada ({$km}) está {$this->kmFormatado($km - $veiculo->km_atual)} km acima da última registrada ({$veiculo->km_atual}). "
                .'Confira o painel; se estiver certa, peça ao gestor para ajustar o km do veículo antes da saída.'
            );
        }
    }

    private function kmFormatado(int $km): string
    {
        return number_format($km, 0, ',', '.');
    }

    private function garantirRascunho(Checagem $checagem, Usuario $quem): void
    {
        if ($checagem->concluida()) {
            throw new \DomainException('Esta checagem já foi concluída.');
        }
        if ($checagem->motorista_id !== $quem->id) {
            throw new \DomainException('Só o motorista da alocação faz a checagem.');
        }

        // A alocação pode ter sido cancelada/concluída depois de o rascunho ser
        // aberto: concluí-lo agora ressuscitaria a alocação.
        $esperada = $checagem->tipo === TipoChecagem::Saida ? SituacaoAlocacao::Aprovada : SituacaoAlocacao::EmUso;
        // value() do Eloquent aplica o cast: volta o enum, não a string.
        $situacaoAtual = Alocacao::whereKey($checagem->alocacao_id)->value('situacao');
        if ($situacaoAtual !== $esperada) {
            throw new \DomainException('Esta alocação não está mais aguardando a checagem de '.mb_strtolower($checagem->tipo->rotulo()).'.');
        }
    }
}
