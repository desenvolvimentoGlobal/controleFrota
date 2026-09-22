<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\LogAuditoria;
use App\Models\Perfil;
use App\Models\Usuario;
use Database\Seeders\PerfilSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutenticacaoTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(string $perfil = 'admin', array $extra = []): Usuario
    {
        $this->seed(PerfilSeeder::class);

        // fresh(): o model recém-criado não carrega os defaults do banco e o
        // Model::shouldBeStrict() recusaria ler `deve_trocar_senha`.
        return Usuario::create(array_merge([
            'nome' => "Usuário {$perfil}",
            'login' => "user.{$perfil}",
            'email' => "{$perfil}@teste.local",
            'cpf' => '11144477735',
            'senha' => 'segredo123',
            'perfil_id' => Perfil::where('codigo', $perfil)->value('id'),
            'ativo' => true,
        ], $extra))->fresh();
    }

    public function test_login_por_login_e_por_email(): void
    {
        $usuario = $this->usuario();

        $this->post('/login', ['acesso' => 'user.admin', 'senha' => 'segredo123'])
            ->assertRedirect(route('painel'));
        $this->assertAuthenticatedAs($usuario);

        auth()->logout();

        $this->post('/login', ['acesso' => 'admin@teste.local', 'senha' => 'segredo123'])
            ->assertRedirect(route('painel'));
        $this->assertAuthenticatedAs($usuario);
    }

    public function test_usuario_inativo_nao_entra(): void
    {
        $this->usuario('geral', ['ativo' => false]);

        $this->from('/')->post('/login', ['acesso' => 'user.geral', 'senha' => 'segredo123'])
            ->assertRedirect('/')
            ->assertSessionHasErrors('acesso');
        $this->assertGuest();
    }

    public function test_login_e_logout_entram_na_auditoria(): void
    {
        $usuario = $this->usuario();

        $this->post('/login', ['acesso' => 'user.admin', 'senha' => 'segredo123']);
        $this->post('/logout');

        $this->assertSame(
            ['login', 'logout'],
            LogAuditoria::where('usuario_id', $usuario->id)->orderBy('id')->pluck('acao')->all(),
        );
    }

    public function test_usuario_com_troca_obrigatoria_e_levado_para_a_tela_de_senha(): void
    {
        $usuario = $this->usuario('geral', ['deve_trocar_senha' => true]);

        $this->actingAs($usuario)->get(route('painel'))->assertRedirect(route('senha.editar'));

        $this->actingAs($usuario)->put(route('senha.atualizar'), [
            'senha_atual' => 'segredo123',
            'senha' => 'novaSenha123',
            'senha_confirmation' => 'novaSenha123',
        ])->assertRedirect(route('painel'));

        $this->assertFalse($usuario->fresh()->deve_trocar_senha);
    }

    public function test_perfil_geral_nao_acessa_usuarios_nem_logs(): void
    {
        $this->actingAs($this->usuario('geral'));

        $this->get(route('usuarios.index'))->assertForbidden();
        $this->get(route('logs.index'))->assertForbidden();
    }
}
