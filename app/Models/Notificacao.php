<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Notificação interna (sino do topbar). */
class Notificacao extends Model
{
    protected $table = 'notificacoes';

    protected $fillable = ['usuario_id', 'tipo', 'titulo', 'mensagem', 'url', 'lida_em'];

    protected function casts(): array
    {
        return ['lida_em' => 'datetime'];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function scopeNaoLidas(Builder $query): Builder
    {
        return $query->whereNull('lida_em');
    }
}
