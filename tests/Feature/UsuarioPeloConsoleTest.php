<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\LogAuditoria;
use App\Models\Perfil;
use App\Models\Usuario;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PerfilSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Primeiro acesso de uma instalação nova e senha esquecida, pelo console. */
class UsuarioPeloConsoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_em_producao_nao_cria_usuario_com_senha_conhecida(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        // O comando exato do roteiro do servidor.
        $this->artisan('db:seed', ['--force' => true, '--class' => DatabaseSeeder::class])->assertSuccessful();

        $this->assertSame(0, Usuario::count(), 'nada de admin/senha123 num servidor exposto');
        $this->assertGreaterThan(0, Perfil::count());
    }

    public function test_cria_o_primeiro_admin_com_senha_definida_as_cegas(): void
    {
        $this->seed(PerfilSeeder::class);

        $this->artisan('usuario:criar', ['--nome' => 'Francisco', '--login' => 'Francisco', '--email' => 'f@global.com.br', '--cpf' => '111.444.777-35', '--perfil' => 'admin'])
            ->expectsConfirmation('Definir a senha agora? (não = o sistema sorteia uma provisória, com troca obrigatória)', 'yes')
            ->expectsQuestion('Senha (não aparece enquanto você digita)', 'segredo123')
            ->expectsQuestion('Repita a senha', 'segredo123')
            ->assertSuccessful();

        $usuario = Usuario::where('login', 'francisco')->firstOrFail();
        $this->assertTrue(Hash::check('segredo123', $usuario->senha));
        $this->assertFalse($usuario->deve_trocar_senha, 'quem cria é quem usa: entra direto');
        $this->assertSame('11144477735', $usuario->cpf);
        $this->assertTrue(LogAuditoria::where('tabela', 'usuarios')->where('registro_id', $usuario->id)->exists());
    }

    public function test_senha_sorteada_obriga_troca_e_senha_fraca_e_recusada(): void
    {
        $this->seed(PerfilSeeder::class);
        $base = ['--nome' => 'Ana', '--email' => 'ana@global.com.br', '--cpf' => '52998224725', '--perfil' => 'gestor'];

        $this->artisan('usuario:criar', $base + ['--login' => 'ana', '--senha' => 'fraca'])->assertFailed();
        $this->assertSame(0, Usuario::count());

        $this->artisan('usuario:criar', $base + ['--login' => 'ana', '--no-interaction' => true])->assertSuccessful();
        $this->assertTrue(Usuario::where('login', 'ana')->firstOrFail()->deve_trocar_senha);

        // Login, e-mail e CPF repetidos: mensagem, não erro de banco.
        $this->artisan('usuario:criar', $base + ['--login' => 'ana', '--no-interaction' => true])->assertFailed();
    }

    public function test_redefine_senha_esquecida_sem_alterar_nada_quando_falha(): void
    {
        $this->seed(PerfilSeeder::class);
        $usuario = Usuario::create([
            'nome' => 'Admin', 'login' => 'admin', 'email' => 'admin@global.com.br', 'cpf' => '11144477735',
            'senha' => 'antiga123', 'perfil_id' => Perfil::where('codigo', 'admin')->value('id'), 'ativo' => true,
        ]);

        // Senha fraca: a antiga continua valendo.
        $this->artisan('usuario:senha', ['login' => 'admin', '--senha' => 'curta', '--no-interaction' => true])->assertFailed();
        $this->assertTrue(Hash::check('antiga123', $usuario->fresh()->senha));

        // Por e-mail, com confirmação mostrando o nome.
        $this->artisan('usuario:senha', ['login' => 'admin@global.com.br', '--sem-troca' => true])
            ->expectsConfirmation('Trocar a senha de Admin?', 'yes')
            ->expectsConfirmation('Definir a senha agora? (não = o sistema sorteia uma provisória, com troca obrigatória)', 'yes')
            ->expectsQuestion('Senha (não aparece enquanto você digita)', 'nova12345')
            ->expectsQuestion('Repita a senha', 'nova12345')
            ->assertSuccessful();

        $this->assertTrue(Hash::check('nova12345', $usuario->fresh()->senha));
        $this->assertFalse($usuario->fresh()->deve_trocar_senha);

        $this->artisan('usuario:senha', ['login' => 'ninguem', '--no-interaction' => true])->assertFailed();
    }
}
