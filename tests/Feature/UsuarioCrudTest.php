<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\LogAuditoria;
use App\Models\Perfil;
use App\Models\Usuario;
use Database\Seeders\PerfilSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsuarioCrudTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(string $perfil, string $cpf, array $extra = []): Usuario
    {
        if (Perfil::count() === 0) {
            $this->seed(PerfilSeeder::class);
        }

        return Usuario::create(array_merge([
            'nome' => "Usuário {$perfil} {$cpf}",
            'login' => "u.{$perfil}.{$cpf}",
            'email' => "{$perfil}.{$cpf}@teste.local",
            'cpf' => $cpf,
            'senha' => 'segredo123',
            'perfil_id' => Perfil::where('codigo', $perfil)->value('id'),
            'ativo' => true,
        ], $extra))->fresh();
    }

    /** @return array<string, mixed> */
    private function dadosValidos(array $extra = []): array
    {
        return array_merge([
            'nome' => 'Maria Motorista',
            'login' => 'Maria.Motorista',
            'email' => 'maria@teste.local',
            'cpf' => '390.533.447-05',
            'perfil_id' => Perfil::where('codigo', 'geral')->value('id'),
            'senha' => 'senhaForte1',
            'pode_dirigir' => 1,
            'cnh_categoria' => 'b',
            'cnh_validade' => now()->addYear()->toDateString(),
            'cep' => '01001-000',
            'uf' => 'sp',
        ], $extra);
    }

    public function test_admin_cria_usuario_com_normalizacao_e_auditoria(): void
    {
        $admin = $this->usuario('admin', '11144477735');

        $this->actingAs($admin)->post(route('usuarios.store'), $this->dadosValidos())
            ->assertRedirect(route('usuarios.index'));

        $novo = Usuario::where('email', 'maria@teste.local')->firstOrFail();
        $this->assertSame('maria.motorista', $novo->login);
        $this->assertSame('39053344705', $novo->cpf);
        $this->assertSame('01001000', $novo->cep);
        $this->assertSame('SP', $novo->uf);
        $this->assertSame('B', $novo->cnh_categoria);
        $this->assertTrue($novo->pode_dirigir);

        $log = LogAuditoria::where('tabela', 'usuarios')->where('acao', 'criou')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame('[PROTEGIDO]', $log->valores_novos['senha']);
    }

    public function test_cpf_invalido_e_login_duplicado_sao_recusados(): void
    {
        $admin = $this->usuario('admin', '11144477735');

        $this->actingAs($admin)->from(route('usuarios.create'))
            ->post(route('usuarios.store'), $this->dadosValidos(['cpf' => '123.456.789-00']))
            ->assertSessionHasErrors('cpf');

        $this->actingAs($admin)->from(route('usuarios.create'))
            ->post(route('usuarios.store'), $this->dadosValidos(['login' => 'u.admin.11144477735']))
            ->assertSessionHasErrors('login');
    }

    public function test_cpf_e_opcional_mas_valido_e_unico_quando_informado(): void
    {
        $admin = $this->usuario('admin', '11144477735');

        // Sem CPF: cadastra, e a ficha e a lista abrem.
        $this->actingAs($admin)->post(route('usuarios.store'), $this->dadosValidos(['cpf' => '']))
            ->assertRedirect(route('usuarios.index'));
        $semCpf = Usuario::where('login', 'maria.motorista')->firstOrFail();
        $this->assertNull($semCpf->cpf);
        $this->actingAs($admin)->get(route('usuarios.show', $semCpf))->assertOk();

        // Dois sem CPF convivem (o unique aceita vários NULL).
        $this->actingAs($admin)->post(route('usuarios.store'), $this->dadosValidos([
            'cpf' => null, 'login' => 'joao', 'email' => 'joao@teste.local', 'nome' => 'João Motorista',
        ]))->assertRedirect(route('usuarios.index'));

        // Editar sem mexer no CPF vazio continua funcionando.
        $this->actingAs($admin)->put(route('usuarios.update', $semCpf), $this->dadosValidos(['cpf' => '', 'senha' => '', 'ativo' => 1]))
            ->assertRedirect(route('usuarios.index'));

        // Informado, continua valendo a regra: válido e único.
        $this->actingAs($admin)->from(route('usuarios.create'))
            ->post(route('usuarios.store'), $this->dadosValidos(['cpf' => '111.444.777-35', 'login' => 'x', 'email' => 'x@teste.local']))
            ->assertSessionHasErrors('cpf');
    }

    public function test_busca_por_nome_nao_traz_todo_mundo(): void
    {
        $admin = $this->usuario('admin', '11144477735');
        $outro = $this->usuario('geral', '39053344705', ['nome' => 'Carlos Souza']);

        // "maria" não tem dígito: antes virava `cpf like '%%'` e casava com todos.
        $this->actingAs($admin)->get(route('usuarios.index', ['busca' => 'maria']))
            ->assertOk()->assertDontSee($outro->nome);

        $this->actingAs($admin)->get(route('usuarios.index', ['busca' => '390.533']))
            ->assertOk()->assertSee($outro->nome);
    }

    public function test_gestor_so_enxerga_e_edita_a_propria_cadeia(): void
    {
        $admin = $this->usuario('admin', '11144477735');
        $gestor = $this->usuario('gestor', '52998224725', ['gestor_id' => $admin->id]);
        $daEquipe = $this->usuario('geral', '15350946056', ['gestor_id' => $gestor->id]);
        $deFora = $this->usuario('geral', '39053344705');

        $this->actingAs($gestor)->get(route('usuarios.index'))
            ->assertOk()
            ->assertSee($daEquipe->nome)
            ->assertDontSee($deFora->nome);

        $this->actingAs($gestor)->get(route('usuarios.edit', $daEquipe))->assertOk();
        $this->actingAs($gestor)->get(route('usuarios.edit', $deFora))->assertForbidden();
        $this->actingAs($gestor)->delete(route('usuarios.destroy', $daEquipe))->assertForbidden();

        // Gestor criando alguém sem informar gestor: a pessoa entra na cadeia dele.
        $this->actingAs($gestor)->post(route('usuarios.store'), $this->dadosValidos([
            'email' => 'nova@teste.local', 'login' => 'nova', 'cpf' => '12345678909',
        ]))->assertRedirect(route('usuarios.index'));

        $this->assertSame($gestor->id, Usuario::where('login', 'nova')->value('gestor_id'));
    }

    public function test_equipe_recursiva_e_protecao_contra_ciclo(): void
    {
        $admin = $this->usuario('admin', '11144477735');
        $gestor = $this->usuario('gestor', '52998224725', ['gestor_id' => $admin->id]);
        $sub = $this->usuario('gestor', '15350946056', ['gestor_id' => $gestor->id]);
        $base = $this->usuario('geral', '39053344705', ['gestor_id' => $sub->id]);

        $this->assertEqualsCanonicalizing([$gestor->id, $sub->id, $base->id], $admin->idsDaEquipe());
        $this->assertEqualsCanonicalizing([$sub->id, $base->id], $gestor->idsDaEquipe());

        // Ciclo artificial: admin passa a responder ao base. Não pode travar.
        $admin->forceFill(['gestor_id' => $base->id])->saveQuietly();
        $this->assertEqualsCanonicalizing([$gestor->id, $sub->id, $base->id], $admin->fresh()->idsDaEquipe());
    }
}
