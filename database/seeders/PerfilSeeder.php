<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\PerfilUsuario;
use App\Models\Perfil;
use Illuminate\Database\Seeder;

class PerfilSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PerfilUsuario::cases() as $perfil) {
            Perfil::updateOrCreate(
                ['codigo' => $perfil->value],
                ['nome' => $perfil->rotulo(), 'descricao' => $perfil->descricao()],
            );
        }
    }
}
