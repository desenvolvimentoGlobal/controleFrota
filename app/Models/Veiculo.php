<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CondicaoVeiculo;
use App\Enums\SituacaoCondicao;
use App\Enums\SituacaoVeiculo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Veiculo extends Model
{
    use SoftDeletes;

    protected $table = 'veiculos';

    protected $fillable = [
        'nome', 'placa', 'chassi', 'renavam', 'foto_path',
        'marca', 'modelo', 'versao', 'ano_fabricacao', 'ano_modelo', 'carroceria', 'cor', 'tipo_cor', 'portas', 'lugares',
        'motor', 'potencia_cv', 'combustivel', 'cambio', 'tracao', 'direcao',
        'ar_condicionado', 'bancos', 'central_multimidia', 'pareamento_smartphone', 'painel_digital', 'vidros_eletricos', 'travas_eletricas',
        'abs', 'esc', 'sensor_ponto_cego', 'cinto_tres_pontos', 'isofix', 'airbags', 'nota_latin_ncap',
        'situacao', 'estado_inicial', 'estado_atual', 'km_inicial', 'km_atual',
        'data_aquisicao', 'valor_aquisicao', 'licenciamento_validade', 'seguro_validade', 'seguradora', 'observacoes',
    ];

    protected function casts(): array
    {
        return [
            'situacao' => SituacaoVeiculo::class,
            'estado_inicial' => CondicaoVeiculo::class,
            'estado_atual' => CondicaoVeiculo::class,
            'central_multimidia' => 'boolean', 'pareamento_smartphone' => 'boolean', 'painel_digital' => 'boolean',
            'vidros_eletricos' => 'boolean', 'travas_eletricas' => 'boolean',
            'abs' => 'boolean', 'esc' => 'boolean', 'sensor_ponto_cego' => 'boolean', 'cinto_tres_pontos' => 'boolean', 'isofix' => 'boolean',
            'data_aquisicao' => 'date', 'licenciamento_validade' => 'date', 'seguro_validade' => 'date',
            'valor_aquisicao' => 'decimal:2',
        ];
    }

    // ─── Relacionamentos ───────────────────────────────────────────────────────

    public function condicoes(): HasMany
    {
        return $this->hasMany(VeiculoCondicao::class, 'veiculo_id')->orderBy('id');
    }

    public function historicoEstados(): HasMany
    {
        return $this->hasMany(VeiculoHistoricoEstado::class, 'veiculo_id')->latest('id');
    }

    // ─── Scopes ────────────────────────────────────────────────────────────────

    public function scopeAtivos(Builder $query): Builder
    {
        return $query->where('situacao', '!=', SituacaoVeiculo::Baixado->value);
    }

    public function scopeDisponiveis(Builder $query): Builder
    {
        return $query->where('situacao', SituacaoVeiculo::Disponivel->value);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function temCondicaoCritica(): bool
    {
        return $this->condicoes->contains(fn (VeiculoCondicao $c) => $c->situacao === SituacaoCondicao::Critico);
    }

    /** @return array<int, string> motivos que impedem a alocação; vazio = pode. */
    public function impedimentosParaAlocar(): array
    {
        $motivos = [];
        if (! $this->situacao->podeSerAlocado()) {
            $motivos[] = 'Situação atual: '.$this->situacao->rotulo().'.';
        }
        if ($this->temCondicaoCritica()) {
            $motivos[] = 'Há sistema mecânico em estado crítico.';
        }

        return $motivos;
    }

    /** @return array<int, string> avisos que não bloqueiam (vencimentos). */
    public function avisos(): array
    {
        $avisos = [];
        $limite = now()->addDays((int) config('frota.alertas.dias_antecedencia_vencimento', 30));

        foreach (['licenciamento_validade' => 'Licenciamento', 'seguro_validade' => 'Seguro'] as $campo => $rotulo) {
            if ($this->{$campo} === null) {
                continue;
            }
            if ($this->{$campo}->isPast()) {
                $avisos[] = "{$rotulo} vencido em ".$this->{$campo}->format('d/m/Y').'.';
            } elseif ($this->{$campo}->lte($limite)) {
                $avisos[] = "{$rotulo} vence em ".$this->{$campo}->format('d/m/Y').'.';
            }
        }

        return $avisos;
    }

    public function getPlacaFormatadaAttribute(): string
    {
        return strlen($this->placa) === 7 ? substr($this->placa, 0, 3).'-'.substr($this->placa, 3) : $this->placa;
    }

    public function getDescricaoAttribute(): string
    {
        return trim("{$this->marca} {$this->modelo} {$this->versao} {$this->ano_modelo}");
    }

    public function getFotoUrlAttribute(): ?string
    {
        return $this->foto_path ? Storage::disk('public')->url($this->foto_path) : null;
    }
}
