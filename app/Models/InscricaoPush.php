<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Inscrição Web Push de um dispositivo/navegador do usuário. */
class InscricaoPush extends Model
{
    protected $table = 'inscricoes_push';

    protected $fillable = ['usuario_id', 'endpoint', 'chave_p256dh', 'chave_auth', 'user_agent'];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
