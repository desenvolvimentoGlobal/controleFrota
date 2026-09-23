<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Entrada da trilha de auditoria. Imutável: nunca é editada nem excluída. */
class LogAuditoria extends Model
{
    protected $table = 'logs_auditoria';

    public const UPDATED_AT = null;

    protected $fillable = [
        'usuario_id', 'acao', 'modulo', 'tabela', 'registro_id',
        'descricao', 'valores_antigos', 'valores_novos', 'ip', 'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'valores_antigos' => 'array',
            'valores_novos' => 'array',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id')->withTrashed();
    }

    /** "alterou_status" → "Alterou status". */
    public static function rotulo(?string $chave): string
    {
        return ucfirst(str_replace('_', ' ', (string) $chave));
    }
}
