<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SituacaoOcorrencia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Anomalia apontada numa checagem, ligada à alocação anterior (responsável presumido). */
class Ocorrencia extends Model
{
    protected $table = 'ocorrencias';

    protected $fillable = [
        'veiculo_id', 'checagem_item_id', 'checagem_item_anterior_id', 'alocacao_responsavel_id', 'apontada_por_id',
        'descricao', 'situacao', 'contestacao', 'contestada_em', 'revisada_por_id', 'revisada_em', 'observacao_revisao', 'manutencao_id',
    ];

    protected function casts(): array
    {
        return [
            'situacao' => SituacaoOcorrencia::class,
            'contestada_em' => 'datetime',
            'revisada_em' => 'datetime',
        ];
    }

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class, 'veiculo_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ChecagemItem::class, 'checagem_item_id');
    }

    public function itemAnterior(): BelongsTo
    {
        return $this->belongsTo(ChecagemItem::class, 'checagem_item_anterior_id');
    }

    public function alocacaoResponsavel(): BelongsTo
    {
        return $this->belongsTo(Alocacao::class, 'alocacao_responsavel_id');
    }

    public function apontadaPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'apontada_por_id');
    }

    public function revisadaPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'revisada_por_id');
    }

    public function manutencao(): BelongsTo
    {
        return $this->belongsTo(Manutencao::class, 'manutencao_id');
    }

    public function scopeAbertas(Builder $query): Builder
    {
        return $query->where('situacao', SituacaoOcorrencia::Aberta->value);
    }

    /** Ocorrências que o usuário enxerga: todas (admin), da cadeia (gestor) ou as que o envolvem. */
    public function scopeVisiveisPara(Builder $query, Usuario $usuario): Builder
    {
        if ($usuario->ehAdmin()) {
            return $query;
        }

        $ids = [$usuario->id, ...($usuario->ehGestor() ? $usuario->idsDaEquipe() : [])];

        return $query->where(fn ($q) => $q
            ->whereIn('apontada_por_id', $ids)
            ->orWhereHas('alocacaoResponsavel', fn ($a) => $a->whereIn('motorista_id', $ids)));
    }

    /** O motorista responsável presumido (se houver alocação anterior). */
    public function responsavel(): ?Usuario
    {
        return $this->alocacaoResponsavel?->motorista;
    }

    public function podeSerContestadaPor(Usuario $usuario): bool
    {
        return $this->situacao === SituacaoOcorrencia::Aberta
            && $this->contestacao === null
            && $this->responsavel()?->id === $usuario->id;
    }
}
