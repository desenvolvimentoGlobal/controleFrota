<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Linha do tempo da manutenção. Imutável. */
class ManutencaoMovimentacao extends Model
{
    protected $table = 'manutencao_movimentacoes';

    public const UPDATED_AT = null;

    protected $fillable = ['manutencao_id', 'usuario_id', 'tipo', 'descricao', 'valores_antigos', 'valores_novos'];

    protected function casts(): array
    {
        return ['valores_antigos' => 'array', 'valores_novos' => 'array'];
    }

    public function manutencao(): BelongsTo
    {
        return $this->belongsTo(Manutencao::class, 'manutencao_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function icone(): string
    {
        return match ($this->tipo) {
            'abertura' => 'bi-plus-circle',
            'situacao' => 'bi-arrow-repeat',
            'alteracao' => 'bi-pencil',
            'anexo' => 'bi-paperclip',
            'comentario' => 'bi-chat-left-text',
            default => 'bi-dot',
        };
    }
}
