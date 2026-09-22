<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CondicaoVeiculo;
use App\Enums\SituacaoVeiculo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Linha do tempo de situação, estado físico e km do veículo. Imutável. */
class VeiculoHistoricoEstado extends Model
{
    protected $table = 'veiculo_historico_estados';

    public const UPDATED_AT = null;

    protected $fillable = ['veiculo_id', 'campo', 'valor_anterior', 'valor_novo', 'origem', 'origem_id', 'observacao', 'usuario_id'];

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class, 'veiculo_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function rotuloCampo(): string
    {
        return match ($this->campo) {
            'situacao' => 'Situação',
            'estado_atual' => 'Estado físico',
            'km_atual' => 'Quilometragem',
            default => $this->campo,
        };
    }

    public function rotuloValor(?string $valor): string
    {
        if ($valor === null) {
            return '—';
        }

        return match ($this->campo) {
            'situacao' => SituacaoVeiculo::rotuloDe($valor),
            'estado_atual' => CondicaoVeiculo::rotuloDe($valor),
            'km_atual' => number_format((int) $valor, 0, ',', '.').' km',
            default => $valor,
        };
    }
}
