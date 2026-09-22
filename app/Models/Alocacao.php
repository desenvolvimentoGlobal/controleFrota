<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CondicaoVeiculo;
use App\Enums\SituacaoAlocacao;
use App\Enums\TipoChecagem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Alocacao extends Model
{
    use SoftDeletes;

    protected $table = 'alocacoes';

    protected $fillable = [
        'veiculo_id', 'motorista_id', 'solicitante_id', 'aprovador_id', 'objetivo', 'destino',
        'saida_prevista', 'retorno_previsto', 'saida_real', 'retorno_real', 'km_saida', 'km_retorno',
        'estado_saida', 'estado_retorno', 'situacao', 'aprovada_em', 'motivo_recusa', 'atrasada_em', 'observacoes',
    ];

    protected function casts(): array
    {
        return [
            'situacao' => SituacaoAlocacao::class,
            'estado_saida' => CondicaoVeiculo::class,
            'estado_retorno' => CondicaoVeiculo::class,
            'saida_prevista' => 'datetime', 'retorno_previsto' => 'datetime',
            'saida_real' => 'datetime', 'retorno_real' => 'datetime',
            'aprovada_em' => 'datetime', 'atrasada_em' => 'datetime',
        ];
    }

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class, 'veiculo_id');
    }

    public function motorista(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'motorista_id');
    }

    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'solicitante_id');
    }

    public function aprovador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'aprovador_id');
    }

    public function checagens(): HasMany
    {
        return $this->hasMany(Checagem::class, 'alocacao_id');
    }

    public function checagemSaida(): HasOne
    {
        return $this->hasOne(Checagem::class, 'alocacao_id')->where('tipo', TipoChecagem::Saida->value);
    }

    public function checagemRetorno(): HasOne
    {
        return $this->hasOne(Checagem::class, 'alocacao_id')->where('tipo', TipoChecagem::Retorno->value);
    }

    public function ocorrenciasComoResponsavel(): HasMany
    {
        return $this->hasMany(Ocorrencia::class, 'alocacao_responsavel_id');
    }

    // ─── Scopes ────────────────────────────────────────────────────────────────

    /** Alocações que o usuário enxerga: todas (admin), a cadeia (gestor) ou as próprias. */
    public function scopeVisiveisPara(Builder $query, Usuario $usuario): Builder
    {
        if ($usuario->ehAdmin()) {
            return $query;
        }

        $ids = [$usuario->id, ...($usuario->ehGestor() ? $usuario->idsDaEquipe() : [])];

        return $query->where(fn ($q) => $q->whereIn('motorista_id', $ids)->orWhereIn('solicitante_id', $ids));
    }

    public function scopeAbertas(Builder $query): Builder
    {
        return $query->whereIn('situacao', SituacaoAlocacao::ocupamAgenda());
    }

    /** Conflito: outra alocação ativa do mesmo veículo cujo intervalo cruza este. */
    public function scopeConflitantes(Builder $query, int $veiculoId, \DateTimeInterface $inicio, \DateTimeInterface $fim, ?int $ignorarId = null): Builder
    {
        return $query->where('veiculo_id', $veiculoId)
            ->abertas()
            ->when($ignorarId, fn ($q) => $q->whereKeyNot($ignorarId))
            ->where('saida_prevista', '<', $fim)
            ->where('retorno_previsto', '>', $inicio);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function atrasada(): bool
    {
        return $this->situacao === SituacaoAlocacao::EmUso && $this->retorno_previsto->isPast();
    }

    /** Qual checagem o motorista precisa fazer agora, se alguma. */
    public function proximaChecagem(): ?TipoChecagem
    {
        return match ($this->situacao) {
            SituacaoAlocacao::Aprovada => TipoChecagem::Saida,
            SituacaoAlocacao::EmUso => TipoChecagem::Retorno,
            default => null,
        };
    }

    public function kmRodados(): ?int
    {
        return $this->km_saida !== null && $this->km_retorno !== null ? $this->km_retorno - $this->km_saida : null;
    }
}
