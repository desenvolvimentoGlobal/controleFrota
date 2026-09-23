<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SituacaoCondicao;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Estado de um sistema mecânico do veículo (motor, freios, pneus...). */
class VeiculoCondicao extends Model
{
    protected $table = 'veiculo_condicoes';

    protected $fillable = ['veiculo_id', 'sistema', 'situacao', 'observacao', 'atualizado_por_id'];

    protected function casts(): array
    {
        return ['situacao' => SituacaoCondicao::class];
    }

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class, 'veiculo_id')->withTrashed();
    }

    public function atualizadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'atualizado_por_id')->withTrashed();
    }

    public function getRotuloSistemaAttribute(): string
    {
        return config("frota.sistemas_mecanicos.{$this->sistema}", $this->sistema);
    }
}
