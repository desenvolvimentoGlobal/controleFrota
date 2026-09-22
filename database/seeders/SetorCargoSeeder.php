<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Cargo;
use App\Models\Setor;
use Illuminate\Database\Seeder;

class SetorCargoSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Administrativo', 'Comercial', 'Instalação', 'Logística', 'Manutenção', 'Financeiro'] as $nome) {
            Setor::firstOrCreate(['nome' => $nome], ['ativo' => true]);
        }

        foreach (['Diretor', 'Gerente', 'Supervisor', 'Analista', 'Assistente', 'Motorista', 'Técnico'] as $nome) {
            Cargo::firstOrCreate(['nome' => $nome], ['ativo' => true]);
        }
    }
}
