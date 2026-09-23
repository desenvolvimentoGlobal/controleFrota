<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PerfilUsuario;
use App\Enums\SituacaoAlocacao;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

/**
 * Usuário do sistema = colaborador (decisão 22/09/2026: uma entidade só).
 * Login por `login` ou `email`; coluna de senha chama-se `senha`.
 */
class Usuario extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $table = 'usuarios';

    protected $fillable = [
        'nome', 'login', 'email', 'senha', 'perfil_id', 'ativo', 'deve_trocar_senha',
        'cpf', 'setor_id', 'cargo_id', 'gestor_id', 'foto_path',
        'telefone', 'celular',
        'cep', 'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'uf',
        'pode_dirigir', 'cnh_numero', 'cnh_categoria', 'cnh_validade',
        'colaborador_externo_id',
    ];

    protected $hidden = ['senha', 'remember_token'];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
            'deve_trocar_senha' => 'boolean',
            'pode_dirigir' => 'boolean',
            'senha' => 'hashed',
            'cnh_validade' => 'date',
            'ultimo_login_em' => 'datetime',
        ];
    }

    /** A coluna foi renomeada de `password` para `senha`. */
    public function getAuthPassword(): string
    {
        return $this->senha;
    }

    public function getEmailForPasswordReset(): string
    {
        return $this->email;
    }

    // ─── Relacionamentos ───────────────────────────────────────────────────────

    public function perfil(): BelongsTo
    {
        return $this->belongsTo(Perfil::class, 'perfil_id');
    }

    public function setor(): BelongsTo
    {
        return $this->belongsTo(Setor::class, 'setor_id');
    }

    public function cargo(): BelongsTo
    {
        return $this->belongsTo(Cargo::class, 'cargo_id');
    }

    public function gestor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'gestor_id');
    }

    public function subordinados(): HasMany
    {
        return $this->hasMany(self::class, 'gestor_id');
    }

    public function notificacoes(): HasMany
    {
        return $this->hasMany(Notificacao::class, 'usuario_id');
    }

    // ─── Perfil ───────────────────────────────────────────────────────────────

    public function temPerfil(string|PerfilUsuario $perfil): bool
    {
        $codigo = $perfil instanceof PerfilUsuario ? $perfil->value : $perfil;

        return $this->perfil?->codigo === $codigo;
    }

    public function temAlgumPerfil(string|PerfilUsuario ...$perfis): bool
    {
        foreach ($perfis as $perfil) {
            if ($this->temPerfil($perfil)) {
                return true;
            }
        }

        return false;
    }

    public function ehAdmin(): bool
    {
        return $this->temPerfil(PerfilUsuario::Admin);
    }

    public function ehGestor(): bool
    {
        return $this->temPerfil(PerfilUsuario::Gestor);
    }

    /** Rota do painel depois do login. Há um painel só, que se adapta ao perfil. */
    public function rotaDashboard(): string
    {
        return 'painel';
    }

    // ─── Hierarquia ───────────────────────────────────────────────────────────

    /**
     * Ids de toda a cadeia abaixo deste usuário (recursivo), sem o próprio.
     * Base do recorte do perfil gestor. Admin não precisa: vê tudo.
     *
     * @return array<int, int>
     */
    public function idsDaEquipe(): array
    {
        $ids = [];
        $fila = [$this->id];

        while ($fila !== []) {
            $filhos = self::whereIn('gestor_id', $fila)->pluck('id')->all();
            $filhos = array_diff($filhos, $ids, [$this->id]); // protege contra ciclo
            $ids = array_merge($ids, $filhos);
            $fila = $filhos;
        }

        return $ids;
    }

    /** Este usuário responde (direta ou indiretamente) pelo outro? */
    public function gerencia(self $outro): bool
    {
        return in_array($outro->id, $this->idsDaEquipe(), true);
    }

    /** Usuários que este pode enxergar: todos (admin) ou a própria cadeia. */
    public function scopeVisiveisPara(Builder $query, self $usuario): Builder
    {
        if ($usuario->ehAdmin()) {
            return $query;
        }

        return $query->whereIn('id', [...$usuario->idsDaEquipe(), $usuario->id]);
    }

    /**
     * Alocação aprovada ou em uso deste motorista. Enquanto existir, ele não
     * pode ser inativado nem excluído: ninguém mais faria o retorno do carro.
     */
    public function alocacaoQueImpedeDesligar(): ?Alocacao
    {
        return Alocacao::with('veiculo:id,nome')
            ->where('motorista_id', $this->id)
            ->whereIn('situacao', [SituacaoAlocacao::Aprovada->value, SituacaoAlocacao::EmUso->value])
            ->first();
    }

    public function motivoParaNaoDesligar(): ?string
    {
        $alocacao = $this->alocacaoQueImpedeDesligar();

        if ($alocacao === null) {
            return null;
        }

        return $alocacao->situacao === SituacaoAlocacao::EmUso
            ? "{$this->nome} está com o veículo {$alocacao->veiculo->nome}. Faça a checagem de retorno ou peça ao admin para encerrar a alocação #{$alocacao->id} antes."
            : "{$this->nome} tem a alocação #{$alocacao->id} aprovada. Cancele-a antes.";
    }

    // ─── Habilitação ──────────────────────────────────────────────────────────

    public function cnhVencida(): bool
    {
        // Validade é o último dia em que a CNH vale.
        return $this->cnh_validade !== null && $this->cnh_validade->lt(today());
    }

    /** Aviso (não bloqueio) exibido ao alocar: sem CNH ou CNH vencida. */
    public function avisoHabilitacao(): ?string
    {
        if (! $this->pode_dirigir) {
            return 'Este usuário não está marcado como apto a dirigir.';
        }
        if ($this->cnh_numero === null) {
            return 'Este usuário não tem CNH cadastrada.';
        }
        if ($this->cnhVencida()) {
            return 'A CNH deste usuário venceu em '.$this->cnh_validade->format('d/m/Y').'.';
        }

        return null;
    }

    // ─── Apresentação ─────────────────────────────────────────────────────────

    public function getCpfFormatadoAttribute(): string
    {
        return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $this->cpf) ?? $this->cpf;
    }

    public function getIniciaisAttribute(): string
    {
        $partes = preg_split('/\s+/', trim($this->nome)) ?: [];
        $primeira = mb_substr($partes[0] ?? '', 0, 1);
        $ultima = count($partes) > 1 ? mb_substr(end($partes), 0, 1) : '';

        return mb_strtoupper($primeira.$ultima);
    }

    public function getFotoUrlAttribute(): ?string
    {
        return $this->foto_path ? Storage::disk('public')->url($this->foto_path) : null;
    }
}
