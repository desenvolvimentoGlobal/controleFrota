<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SituacaoItemChecagem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ChecagemItem extends Model
{
    protected $table = 'checagem_itens';

    protected $fillable = ['checagem_id', 'categoria', 'item', 'situacao', 'observacao'];

    protected function casts(): array
    {
        return ['situacao' => SituacaoItemChecagem::class];
    }

    public function checagem(): BelongsTo
    {
        return $this->belongsTo(Checagem::class, 'checagem_id');
    }

    public function fotos(): HasMany
    {
        return $this->hasMany(ChecagemFoto::class, 'checagem_item_id')->orderBy('id');
    }

    /** A foto mais recente do item (a que vale para comparação). */
    public function fotoAtual(): HasOne
    {
        return $this->hasOne(ChecagemFoto::class, 'checagem_item_id')->latestOfMany();
    }

    public function ocorrencia(): HasOne
    {
        return $this->hasOne(Ocorrencia::class, 'checagem_item_id');
    }

    /** Ocorrências em que este item foi a foto de comparação. */
    public function ocorrenciaComoAnterior(): HasMany
    {
        return $this->hasMany(Ocorrencia::class, 'checagem_item_anterior_id');
    }

    public function getRotuloAttribute(): string
    {
        return config("frota.checagem.categorias.{$this->categoria}.itens.{$this->item}", $this->item);
    }

    public function getRotuloCategoriaAttribute(): string
    {
        return config("frota.checagem.categorias.{$this->categoria}.rotulo", $this->categoria);
    }
}
