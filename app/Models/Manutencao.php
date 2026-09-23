<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SituacaoManutencao;
use App\Enums\TipoManutencao;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Manutencao extends Model
{
    use SoftDeletes;

    protected $table = 'manutencoes';

    protected $fillable = [
        'veiculo_id', 'tipo', 'nome', 'descricao_problema', 'fornecedor_id', 'preco_previsto', 'preco_final',
        'prazo', 'localizacao', 'km_abertura', 'km_conclusao', 'situacao', 'sistemas', 'bloqueou_veiculo', 'aberta_por_id', 'responsavel_id',
        'ocorrencia_id', 'plano_manutencao_id', 'inicio_prestacao_em', 'situacao_antes_prestacao', 'concluida_em', 'motivo_cancelamento', 'observacoes',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoManutencao::class,
            'situacao' => SituacaoManutencao::class,
            'sistemas' => 'array',
            'bloqueou_veiculo' => 'boolean',
            'preco_previsto' => 'decimal:2',
            'preco_final' => 'decimal:2',
            'prazo' => 'date',
            'inicio_prestacao_em' => 'datetime',
            'concluida_em' => 'datetime',
        ];
    }

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class, 'veiculo_id')->withTrashed();
    }

    public function fornecedor(): BelongsTo
    {
        return $this->belongsTo(Fornecedor::class, 'fornecedor_id')->withTrashed();
    }

    public function abertaPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'aberta_por_id')->withTrashed();
    }

    public function responsavel(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'responsavel_id')->withTrashed();
    }

    public function ocorrencia(): BelongsTo
    {
        return $this->belongsTo(Ocorrencia::class, 'ocorrencia_id');
    }

    public function plano(): BelongsTo
    {
        return $this->belongsTo(PlanoManutencao::class, 'plano_manutencao_id');
    }

    public function movimentacoes(): HasMany
    {
        return $this->hasMany(ManutencaoMovimentacao::class, 'manutencao_id')->latest('id');
    }

    public function anexos(): HasMany
    {
        return $this->hasMany(ManutencaoAnexo::class, 'manutencao_id')->orderBy('id');
    }

    public function scopeAbertas(Builder $query): Builder
    {
        return $query->whereIn('situacao', SituacaoManutencao::abertas());
    }

    public function atrasada(): bool
    {
        return $this->situacao->aberta() && $this->prazo !== null && $this->prazo->endOfDay()->isPast();
    }

    /** Valor que conta como custo: o final, se houver; senão o previsto. */
    public function custo(): ?string
    {
        return $this->preco_final ?? $this->preco_previsto;
    }

    /** @return array<int, string> rótulos dos sistemas tratados */
    public function rotulosSistemas(): array
    {
        return array_map(fn (string $s) => config("frota.sistemas_mecanicos.{$s}", $s), $this->sistemas ?? []);
    }
}
