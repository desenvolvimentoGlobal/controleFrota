<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CondicaoVeiculo;
use App\Enums\SituacaoCondicao;
use App\Enums\SituacaoManutencao;
use App\Enums\SituacaoVeiculo;
use App\Enums\TipoManutencao;
use App\Models\Fornecedor;
use App\Models\Manutencao;
use App\Models\ManutencaoAnexo;
use App\Models\Ocorrencia;
use App\Models\PlanoManutencao;
use App\Models\Usuario;
use App\Models\Veiculo;
use App\Support\Numero;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Ciclo da manutenção (docs/PLANEJAMENTO.md 3.4):
 *   em_espera → em_prestacao → prestada (+ cancelada)
 *
 * Toda ação grava uma movimentação (linha do tempo). O veículo só muda pelo
 * VeiculoService, com origem `manutencao`:
 *   - abertura com "bloquear veículo": indisponivel;
 *   - início da prestação: em_manutencao;
 *   - conclusão/cancelamento: volta a disponivel se nenhuma outra
 *     manutenção o mantiver parado.
 */
class ManutencaoService
{
    public const DISCO = 'local';

    /** Campos cuja alteração vira movimentação legível. */
    private const CAMPOS_ROTULADOS = [
        'tipo' => 'tipo', 'nome' => 'nome', 'descricao_problema' => 'descrição do problema',
        'fornecedor_id' => 'fornecedor', 'preco_previsto' => 'preço previsto', 'preco_final' => 'preço final',
        'prazo' => 'prazo', 'localizacao' => 'localização', 'responsavel_id' => 'responsável',
        'sistemas' => 'sistemas tratados', 'observacoes' => 'observações',
    ];

    public function __construct(
        private readonly VeiculoService $veiculos,
        private readonly NotificacaoService $notificar,
    ) {}

    /**
     * @param  array<string, mixed>  $dados
     */
    public function abrir(array $dados, ?Usuario $quem): Manutencao
    {
        $veiculo = Veiculo::findOrFail($dados['veiculo_id']);

        if ($veiculo->situacao === SituacaoVeiculo::Baixado) {
            throw new \DomainException('Não é possível abrir manutenção para um veículo baixado.');
        }

        $ocorrencia = null;
        if (! empty($dados['ocorrencia_id'])) {
            $ocorrencia = Ocorrencia::findOrFail($dados['ocorrencia_id']);
            if ($ocorrencia->veiculo_id !== $veiculo->id) {
                throw new \DomainException('A ocorrência informada é de outro veículo.');
            }
            if ($ocorrencia->manutencao_id !== null && Manutencao::whereKey($ocorrencia->manutencao_id)->abertas()->exists()) {
                throw new \DomainException("A ocorrência #{$ocorrencia->id} já tem uma manutenção aberta.");
            }
        }

        $bloquear = (bool) ($dados['bloquear_veiculo'] ?? false);

        $manutencao = DB::transaction(function () use ($dados, $veiculo, $quem, $ocorrencia, $bloquear): Manutencao {
            $manutencao = Manutencao::create([
                'veiculo_id' => $veiculo->id,
                'tipo' => $dados['tipo'],
                'nome' => $dados['nome'],
                'descricao_problema' => $dados['descricao_problema'] ?? null,
                'fornecedor_id' => $dados['fornecedor_id'] ?? null,
                'preco_previsto' => $dados['preco_previsto'] ?? null,
                'prazo' => $dados['prazo'] ?? null,
                'localizacao' => $dados['localizacao'] ?? null,
                'responsavel_id' => $dados['responsavel_id'] ?? null,
                'sistemas' => array_values($dados['sistemas'] ?? []) ?: null,
                'observacoes' => $dados['observacoes'] ?? null,
                'ocorrencia_id' => $ocorrencia?->id,
                'plano_manutencao_id' => $dados['plano_manutencao_id'] ?? null,
                'km_abertura' => $veiculo->km_atual,
                'situacao' => SituacaoManutencao::EmEspera->value,
                'aberta_por_id' => $quem?->id,
            ]);

            $ocorrencia?->update(['manutencao_id' => $manutencao->id]);

            $origem = $ocorrencia ? " a partir da ocorrência #{$ocorrencia->id}" : ($manutencao->plano_manutencao_id ? ' pelo plano preventivo' : '');
            $this->movimentar($manutencao, 'abertura', "Manutenção {$manutencao->tipo->rotulo()} aberta{$origem}.", null, null, $quem);

            // Bloqueio imediato: veículo com defeito não deve ser alocado
            // enquanto espera a oficina. Em uso, ele termina a alocação antes.
            if ($bloquear && in_array($veiculo->situacao, [SituacaoVeiculo::Disponivel, SituacaoVeiculo::Reservado], true)) {
                $this->veiculos->mudarSituacao($veiculo, SituacaoVeiculo::Indisponivel, 'manutencao', $manutencao->id, "Bloqueado pela manutenção #{$manutencao->id}");
                $manutencao->update(['bloqueou_veiculo' => true]);
                $this->movimentar($manutencao, 'situacao', 'Veículo marcado como indisponível até a conclusão.', null, null, $quem);
            }

            return $manutencao;
        });

        $this->notificar->enviar(
            $this->notificar->comPerfis(['admin', 'financeiro'], $quem?->id),
            'manutencao_aberta',
            "Manutenção aberta: {$veiculo->nome}",
            ($quem?->nome ?? 'O sistema')." abriu \"{$manutencao->nome}\" ({$manutencao->tipo->rotulo()})".
                ($manutencao->preco_previsto !== null ? ', previsto '.Numero::moeda($manutencao->preco_previsto) : '').'.',
            route('manutencoes.show', $manutencao, false),
        );

        return $manutencao;
    }

    /**
     * Edição dos dados enquanto a manutenção está aberta.
     *
     * @param  array<string, mixed>  $dados
     */
    public function atualizar(Manutencao $manutencao, array $dados, Usuario $quem): Manutencao
    {
        if (! $manutencao->situacao->aberta()) {
            throw new \DomainException('Só manutenções em espera ou em prestação podem ser editadas.');
        }

        return DB::transaction(function () use ($manutencao, $dados, $quem): Manutencao {
            $permitidos = array_intersect_key($dados, self::CAMPOS_ROTULADOS);
            if (array_key_exists('sistemas', $permitidos)) {
                $permitidos['sistemas'] = array_values($permitidos['sistemas'] ?? []) ?: null;
            }

            $manutencao->fill($permitidos);
            $alterados = $manutencao->getDirty();

            if ($alterados === []) {
                return $manutencao;
            }

            $antigos = array_intersect_key($manutencao->getOriginal(), $alterados);
            $manutencao->save();

            $this->movimentar(
                $manutencao, 'alteracao',
                'Alterou '.implode(', ', array_map(fn ($c) => self::CAMPOS_ROTULADOS[$c] ?? $c, array_keys($alterados))).'.',
                $this->legivel($antigos), $this->legivel($alterados), $quem,
            );

            return $manutencao;
        });
    }

    public function iniciarPrestacao(Manutencao $manutencao, Usuario $quem): void
    {
        if ($manutencao->situacao !== SituacaoManutencao::EmEspera) {
            throw new \DomainException('Só manutenções em espera podem entrar em prestação.');
        }

        $veiculo = $manutencao->veiculo;
        if (in_array($veiculo->situacao, [SituacaoVeiculo::EmUso, SituacaoVeiculo::Reservado], true)) {
            throw new \DomainException("O veículo está {$veiculo->situacao->rotulo()}. Conclua ou cancele a alocação antes de enviá-lo à manutenção.");
        }

        DB::transaction(function () use ($manutencao, $veiculo, $quem): void {
            $manutencao->update([
                'situacao' => SituacaoManutencao::EmPrestacao->value,
                'inicio_prestacao_em' => now(),
            ]);

            $this->veiculos->mudarSituacao($veiculo, SituacaoVeiculo::EmManutencao, 'manutencao', $manutencao->id, "Manutenção #{$manutencao->id}: {$manutencao->nome}");
            $this->movimentar($manutencao, 'situacao', 'Prestação iniciada. Veículo em manutenção.', ['situacao' => 'Em espera'], ['situacao' => 'Em prestação'], $quem);
        });

        $this->avisarMudanca($manutencao, $quem, 'entrou em prestação');
    }

    /**
     * @param  array{preco_final?: string|null, km_conclusao?: int|null, estado_atual?: string|null, observacao?: string|null}  $dados
     */
    public function concluir(Manutencao $manutencao, array $dados, Usuario $quem): void
    {
        if ($manutencao->situacao !== SituacaoManutencao::EmPrestacao) {
            throw new \DomainException('Só manutenções em prestação podem ser concluídas.');
        }

        $veiculo = $manutencao->veiculo;
        $km = isset($dados['km_conclusao']) && $dados['km_conclusao'] !== null ? (int) $dados['km_conclusao'] : $veiculo->km_atual;

        if ($km < $veiculo->km_atual) {
            throw new \DomainException("A quilometragem informada ({$km}) é menor que a atual do veículo ({$veiculo->km_atual}).");
        }

        DB::transaction(function () use ($manutencao, $veiculo, $km, $dados, $quem): void {
            $manutencao->update([
                'situacao' => SituacaoManutencao::Prestada->value,
                'concluida_em' => now(),
                'preco_final' => $dados['preco_final'] ?? $manutencao->preco_previsto,
                'km_conclusao' => $km,
            ]);

            $this->veiculos->atualizarKm($veiculo, $km, 'manutencao', $manutencao->id, "Conclusão da manutenção #{$manutencao->id}");

            if (! empty($dados['estado_atual'])) {
                $this->veiculos->mudarEstadoFisico($veiculo, CondicaoVeiculo::from($dados['estado_atual']), 'manutencao', $manutencao->id, "Conclusão da manutenção #{$manutencao->id}");
            }

            // Sistemas tratados voltam a OK.
            foreach ($manutencao->sistemas ?? [] as $sistema) {
                $veiculo->condicoes()->where('sistema', $sistema)->update([
                    'situacao' => SituacaoCondicao::Ok->value,
                    'observacao' => "Tratado na manutenção #{$manutencao->id} em ".now()->format('d/m/Y').'.',
                    'atualizado_por_id' => $quem->id,
                    'updated_at' => now(),
                ]);
            }

            if ($manutencao->plano) {
                $manutencao->plano->update(['ultimo_km' => $km, 'ultima_data' => today()]);
            }

            $observacao = trim((string) ($dados['observacao'] ?? ''));
            $this->movimentar(
                $manutencao, 'situacao',
                'Manutenção concluída. Custo final '.Numero::moeda($manutencao->preco_final).', '.Numero::km($km).'.'.($observacao !== '' ? " {$observacao}" : ''),
                ['situacao' => 'Em prestação'], ['situacao' => 'Prestada', 'preco_final' => $manutencao->preco_final], $quem,
            );

            $this->liberarVeiculo($manutencao, "Manutenção #{$manutencao->id} concluída");
        });

        $this->avisarMudanca($manutencao, $quem, 'foi concluída ('.Numero::moeda($manutencao->preco_final).')');
    }

    public function cancelar(Manutencao $manutencao, Usuario $quem, string $motivo): void
    {
        if (! $manutencao->situacao->aberta()) {
            throw new \DomainException('Esta manutenção já foi encerrada.');
        }

        DB::transaction(function () use ($manutencao, $quem, $motivo): void {
            $anterior = $manutencao->situacao->rotulo();
            $manutencao->update(['situacao' => SituacaoManutencao::Cancelada->value, 'motivo_cancelamento' => $motivo]);

            // A ocorrência volta a poder gerar outra manutenção.
            Ocorrencia::where('manutencao_id', $manutencao->id)->update(['manutencao_id' => null]);

            $this->movimentar($manutencao, 'situacao', "Manutenção cancelada: {$motivo}", ['situacao' => $anterior], ['situacao' => 'Cancelada'], $quem);
            $this->liberarVeiculo($manutencao, "Manutenção #{$manutencao->id} cancelada");
        });

        $this->avisarMudanca($manutencao, $quem, "foi cancelada: {$motivo}");
    }

    public function comentar(Manutencao $manutencao, Usuario $quem, string $texto): void
    {
        $this->movimentar($manutencao, 'comentario', $texto, null, null, $quem);
    }

    public function anexar(Manutencao $manutencao, UploadedFile $arquivo, ?string $titulo, Usuario $quem): ManutencaoAnexo
    {
        return DB::transaction(function () use ($manutencao, $arquivo, $titulo, $quem): ManutencaoAnexo {
            $caminho = $arquivo->storeAs(
                "manutencoes/{$manutencao->id}",
                Str::uuid().'.'.($arquivo->extension() ?: 'bin'),
                self::DISCO,
            );

            $anexo = $manutencao->anexos()->create([
                'titulo' => $titulo ?: null,
                'caminho' => $caminho,
                'nome_original' => mb_substr((string) $arquivo->getClientOriginalName(), 0, 255),
                'mime' => $arquivo->getMimeType(),
                'tamanho' => $arquivo->getSize(),
                'enviado_por_id' => $quem->id,
            ]);

            $this->movimentar($manutencao, 'anexo', 'Anexou '.($titulo ?: $anexo->nome_original).'.', null, null, $quem);

            return $anexo;
        });
    }

    public function removerAnexo(ManutencaoAnexo $anexo, Usuario $quem): void
    {
        DB::transaction(function () use ($anexo, $quem): void {
            Storage::disk(self::DISCO)->delete($anexo->caminho);
            $nome = $anexo->titulo ?: $anexo->nome_original;
            $manutencao = $anexo->manutencao;
            $anexo->delete();
            $this->movimentar($manutencao, 'anexo', "Removeu o anexo {$nome}.", null, null, $quem);
        });
    }

    /**
     * Scheduler: abre manutenção preventiva para os planos vencidos que
     * ainda não têm uma aberta.
     *
     * @return int quantidade aberta
     */
    public function verificarPlanos(): int
    {
        $abertas = 0;

        $planos = PlanoManutencao::with('veiculo')
            ->where('ativo', true)
            ->whereHas('veiculo', fn ($q) => $q->where('situacao', '!=', SituacaoVeiculo::Baixado->value))
            ->get();

        foreach ($planos as $plano) {
            if (! $plano->vencido($plano->veiculo->km_atual) || $plano->temManutencaoAberta()) {
                continue;
            }

            $motivo = array_filter([
                $plano->proximoKm() !== null ? 'previsto em '.Numero::km($plano->proximoKm()).' (atual '.Numero::km($plano->veiculo->km_atual).')' : null,
                $plano->proximaData() !== null ? 'previsto para '.$plano->proximaData()->format('d/m/Y') : null,
            ]);

            $this->abrir([
                'veiculo_id' => $plano->veiculo_id,
                'tipo' => TipoManutencao::Preventiva->value,
                'nome' => $plano->nome,
                'descricao_problema' => "Revisão periódica ({$plano->descricaoIntervalo()}): ".implode('; ', $motivo).'.',
                'plano_manutencao_id' => $plano->id,
            ], null);

            $abertas++;
        }

        return $abertas;
    }

    // ─── Apoio ─────────────────────────────────────────────────────────────────

    /** Devolve o veículo a disponível se nada mais o mantiver parado. */
    private function liberarVeiculo(Manutencao $manutencao, string $motivo): void
    {
        $veiculo = $manutencao->veiculo->fresh();

        $outrasEmPrestacao = Manutencao::where('veiculo_id', $veiculo->id)->whereKeyNot($manutencao->id)
            ->where('situacao', SituacaoManutencao::EmPrestacao->value)->exists();
        $outrasBloqueando = Manutencao::where('veiculo_id', $veiculo->id)->whereKeyNot($manutencao->id)
            ->abertas()->where('bloqueou_veiculo', true)->exists();

        if ($veiculo->situacao === SituacaoVeiculo::EmManutencao && ! $outrasEmPrestacao) {
            $destino = $outrasBloqueando ? SituacaoVeiculo::Indisponivel : SituacaoVeiculo::Disponivel;
            $this->veiculos->mudarSituacao($veiculo, $destino, 'manutencao', $manutencao->id, $motivo);
        } elseif ($veiculo->situacao === SituacaoVeiculo::Indisponivel && $manutencao->bloqueou_veiculo && ! $outrasBloqueando && ! $outrasEmPrestacao) {
            $this->veiculos->mudarSituacao($veiculo, SituacaoVeiculo::Disponivel, 'manutencao', $manutencao->id, $motivo);
        }
    }

    /**
     * @param  array<string, mixed>|null  $antigos
     * @param  array<string, mixed>|null  $novos
     */
    private function movimentar(Manutencao $manutencao, string $tipo, string $descricao, ?array $antigos, ?array $novos, ?Usuario $quem): void
    {
        $manutencao->movimentacoes()->create([
            'usuario_id' => $quem?->id,
            'tipo' => $tipo,
            'descricao' => mb_substr($descricao, 0, 1000),
            'valores_antigos' => $antigos,
            'valores_novos' => $novos,
        ]);
    }

    /**
     * Converte ids e datas em texto para a linha do tempo.
     *
     * @param  array<string, mixed>  $valores
     * @return array<string, mixed>
     */
    private function legivel(array $valores): array
    {
        $saida = [];
        foreach ($valores as $campo => $valor) {
            $rotulo = self::CAMPOS_ROTULADOS[$campo] ?? $campo;
            $saida[$rotulo] = match ($campo) {
                'fornecedor_id' => $valor ? Fornecedor::withTrashed()->find($valor)?->nome : null,
                'responsavel_id' => $valor ? Usuario::find($valor)?->nome : null,
                'tipo' => $valor ? TipoManutencao::rotuloDe(is_string($valor) ? $valor : $valor->value) : null,
                'preco_previsto', 'preco_final' => $valor !== null ? Numero::moeda($valor) : null,
                'prazo' => $valor ? Carbon::parse($valor)->format('d/m/Y') : null,
                'sistemas' => implode(', ', array_map(fn ($s) => config("frota.sistemas_mecanicos.{$s}", $s), is_string($valor) ? (json_decode($valor, true) ?: []) : ($valor ?? []))),
                default => $valor,
            };
        }

        return $saida;
    }

    private function avisarMudanca(Manutencao $manutencao, Usuario $quem, string $acontecimento): void
    {
        $destinatarios = $this->notificar->comPerfis(['financeiro'], $quem->id);
        if ($manutencao->abertaPor && $manutencao->aberta_por_id !== $quem->id) {
            $destinatarios->push($manutencao->abertaPor);
        }

        $this->notificar->enviar(
            $destinatarios->unique('id'),
            'manutencao_situacao',
            "Manutenção #{$manutencao->id} — {$manutencao->veiculo->nome}",
            "\"{$manutencao->nome}\" {$acontecimento}.",
            route('manutencoes.show', $manutencao, false),
        );
    }
}
