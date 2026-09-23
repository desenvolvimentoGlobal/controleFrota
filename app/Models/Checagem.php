<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CondicaoVeiculo;
use App\Enums\SituacaoItemChecagem;
use App\Enums\TipoChecagem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Checagem extends Model
{
    protected $table = 'checagens';

    public const NIVEIS_COMBUSTIVEL = [
        'vazio' => 'Reserva / vazio', 'quarto' => '1/4', 'meio' => '1/2', 'tres_quartos' => '3/4', 'cheio' => 'Cheio',
    ];

    protected $fillable = [
        'alocacao_id', 'veiculo_id', 'motorista_id', 'tipo', 'checagem_anterior_id', 'km_informado', 'nivel_combustivel',
        'estado_geral', 'situacao', 'concluida_em', 'observacao_motorista', 'revisada_por_id', 'revisada_em', 'observacao_revisao',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoChecagem::class,
            'estado_geral' => CondicaoVeiculo::class,
            'concluida_em' => 'datetime',
            'revisada_em' => 'datetime',
        ];
    }

    public function alocacao(): BelongsTo
    {
        return $this->belongsTo(Alocacao::class, 'alocacao_id');
    }

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class, 'veiculo_id')->withTrashed();
    }

    public function motorista(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'motorista_id')->withTrashed();
    }

    public function anterior(): BelongsTo
    {
        return $this->belongsTo(self::class, 'checagem_anterior_id');
    }

    public function revisadaPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'revisada_por_id')->withTrashed();
    }

    public function itens(): HasMany
    {
        return $this->hasMany(ChecagemItem::class, 'checagem_id')->orderBy('id');
    }

    public function scopeConcluidas(Builder $query): Builder
    {
        return $query->where('situacao', 'concluida');
    }

    public function concluida(): bool
    {
        return $this->situacao === 'concluida';
    }

    /** Itens ainda sem foto ou sem resposta. */
    public function itensPendentes(): int
    {
        // Usa a relação que a tela carregou (fotoAtual ou fotos): com o modo
        // estrito do Eloquent, acessar uma não carregada dá erro em coleção.
        $semFoto = fn (ChecagemItem $i) => $i->relationLoaded('fotoAtual') ? $i->fotoAtual === null : $i->fotos->isEmpty();

        return $this->itens->filter(fn (ChecagemItem $i) => $i->situacao === SituacaoItemChecagem::Pendente || $semFoto($i))->count();
    }

    public function itensComAnomalia(): int
    {
        return $this->itens->where('situacao', SituacaoItemChecagem::Anomalia)->count();
    }

    public function rotuloCombustivel(): string
    {
        return self::NIVEIS_COMBUSTIVEL[$this->nivel_combustivel] ?? '—';
    }
}
