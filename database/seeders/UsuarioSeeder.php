<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Cargo;
use App\Models\Perfil;
use App\Models\Setor;
use App\Models\Usuario;
use Illuminate\Database\Seeder;

/**
 * Usuários iniciais. Em produção só o admin deve existir (troque a senha no
 * primeiro acesso: `deve_trocar_senha`). Os demais são de desenvolvimento.
 */
class UsuarioSeeder extends Seeder
{
    public function run(): void
    {
        $perfil = fn (string $codigo) => Perfil::where('codigo', $codigo)->value('id');
        $setor = fn (string $nome) => Setor::where('nome', $nome)->value('id');
        $cargo = fn (string $nome) => Cargo::where('nome', $nome)->value('id');

        $admin = Usuario::updateOrCreate(
            ['login' => 'admin'],
            [
                'nome' => 'Administrador',
                'email' => 'admin@global.com.br',
                'cpf' => '11144477735',
                'senha' => 'senha123',
                'perfil_id' => $perfil('admin'),
                'setor_id' => $setor('Administrativo'),
                'cargo_id' => $cargo('Diretor'),
                'ativo' => true,
                'deve_trocar_senha' => app()->isProduction(),
            ],
        );

        if (app()->isProduction()) {
            return;
        }

        $gestor = Usuario::updateOrCreate(
            ['login' => 'gestor'],
            [
                'nome' => 'Gestor de Logística',
                'email' => 'gestor@global.com.br',
                'cpf' => '52998224725',
                'senha' => 'senha123',
                'perfil_id' => $perfil('gestor'),
                'setor_id' => $setor('Logística'),
                'cargo_id' => $cargo('Supervisor'),
                'gestor_id' => $admin->id,
                'pode_dirigir' => true,
                'cnh_numero' => '12345678901',
                'cnh_categoria' => 'B',
                'cnh_validade' => now()->addYears(3)->toDateString(),
                'ativo' => true,
            ],
        );

        Usuario::updateOrCreate(
            ['login' => 'motorista'],
            [
                'nome' => 'Motorista Geral',
                'email' => 'motorista@global.com.br',
                'cpf' => '15350946056',
                'senha' => 'senha123',
                'perfil_id' => $perfil('geral'),
                'setor_id' => $setor('Logística'),
                'cargo_id' => $cargo('Motorista'),
                'gestor_id' => $gestor->id,
                'pode_dirigir' => true,
                'cnh_numero' => '98765432100',
                'cnh_categoria' => 'AB',
                'cnh_validade' => now()->addDays(20)->toDateString(), // vence em breve: exercita o alerta
                'ativo' => true,
            ],
        );

        Usuario::updateOrCreate(
            ['login' => 'financeiro'],
            [
                'nome' => 'Analista Financeiro',
                'email' => 'financeiro@global.com.br',
                'cpf' => '39053344705',
                'senha' => 'senha123',
                'perfil_id' => $perfil('financeiro'),
                'setor_id' => $setor('Financeiro'),
                'cargo_id' => $cargo('Analista'),
                'gestor_id' => $admin->id,
                'ativo' => true,
            ],
        );
    }
}
