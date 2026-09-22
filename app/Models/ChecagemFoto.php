<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Foto de um item de checagem. Arquivo no disco PRIVADO; servido por rota autenticada. */
class ChecagemFoto extends Model
{
    protected $table = 'checagem_fotos';

    protected $fillable = ['checagem_item_id', 'caminho', 'nome_original', 'mime', 'tamanho', 'enviada_por_id', 'apagada_em'];

    protected function casts(): array
    {
        return ['apagada_em' => 'datetime'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ChecagemItem::class, 'checagem_item_id');
    }

    public function disponivel(): bool
    {
        return $this->apagada_em === null;
    }
}
