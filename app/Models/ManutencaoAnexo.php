<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Orçamento, nota fiscal ou foto da manutenção. Disco PRIVADO, rota autenticada. */
class ManutencaoAnexo extends Model
{
    protected $table = 'manutencao_anexos';

    protected $fillable = ['manutencao_id', 'titulo', 'caminho', 'nome_original', 'mime', 'tamanho', 'enviado_por_id'];

    public function manutencao(): BelongsTo
    {
        return $this->belongsTo(Manutencao::class, 'manutencao_id');
    }

    public function enviadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'enviado_por_id');
    }

    public function ehImagem(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
    }

    public function tamanhoLegivel(): string
    {
        $bytes = (int) $this->tamanho;

        return $bytes >= 1048576 ? number_format($bytes / 1048576, 1, ',', '.').' MB' : number_format($bytes / 1024, 0, ',', '.').' KB';
    }
}
