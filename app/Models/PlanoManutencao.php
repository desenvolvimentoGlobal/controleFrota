<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SituacaoManutencao;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Revisão periódica de um veículo, por km e/ou por dias (o que vencer
 * primeiro). Sem última execução registrada, a base é o km e a data do
 * cadastro do plano.
 */
class PlanoManutencao extends Model
{
    use SoftDeletes;

    protected $table = 'planos_manutencao';

    protected $fillable = ['veiculo_id', 'nome', 'intervalo_km', 'intervalo_dias', 'ultimo_km', 'ultima_data', 'ativo'];

    protected function casts(): array
    {
        return ['ultima_data' => 'date', 'ativo' => 'boolean'];
    }

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class, 'veiculo_id')->withTrashed();
    }

    public function manutencoes(): HasMany
    {
        return $this->hasMany(Manutencao::class, 'plano_manutencao_id');
    }

    public function proximoKm(): ?int
    {
        return $this->intervalo_km ? (int) ($this->ultimo_km ?? 0) + $this->intervalo_km : null;
    }

    public function proximaData(): ?CarbonInterface
    {
        if (! $this->intervalo_dias) {
            return null;
        }

        return ($this->ultima_data ?? $this->created_at)->copy()->addDays($this->intervalo_dias);
    }

    /** Vence agora (ou dentro da antecedência configurada)? */
    public function vencido(int $kmAtual): bool
    {
        $antecedenciaKm = (int) config('frota.manutencao.antecedencia_km', 500);
        $antecedenciaDias = (int) config('frota.manutencao.antecedencia_dias', 7);

        $porKm = $this->proximoKm() !== null && $kmAtual >= $this->proximoKm() - $antecedenciaKm;
        $porData = $this->proximaData() !== null && now()->addDays($antecedenciaDias)->gte($this->proximaData());

        return $porKm || $porData;
    }

    public function temManutencaoAberta(): bool
    {
        return $this->manutencoes()->whereIn('situacao', SituacaoManutencao::abertas())->exists();
    }

    public function descricaoIntervalo(): string
    {
        $partes = array_filter([
            $this->intervalo_km ? 'a cada '.number_format($this->intervalo_km, 0, ',', '.').' km' : null,
            $this->intervalo_dias ? "a cada {$this->intervalo_dias} dias" : null,
        ]);

        return implode(' ou ', $partes);
    }
}
