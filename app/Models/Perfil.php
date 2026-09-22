<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Perfil extends Model
{
    protected $table = 'perfis';

    protected $fillable = ['nome', 'codigo', 'descricao'];

    public function usuarios(): HasMany
    {
        return $this->hasMany(Usuario::class, 'perfil_id');
    }
}
