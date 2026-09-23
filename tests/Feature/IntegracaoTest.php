<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SituacaoVeiculo;
use App\Models\Alocacao;
use App\Models\Notificacao;
use App\Models\Perfil;
use App\Models\TokenIntegracao;
use App\Models\Usuario;
use App\Models\Veiculo;
use App\Services\VeiculoService;
use Database\Seeders\PerfilSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IntegracaoTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $admin;

    private Usuario $gestor;

    private Veiculo $onix;

    private Veiculo $strada;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PerfilSeeder::class);

        $this->admin = $this->usuario('admin', 'admin', '11144477735');
        $this->gestor = $this->usuario('gestor', 'gestor', '52998224725', ['pode_dirigir' => true, 'gestor_id' => $this->admin->id]);

        $this->actingAs($this->admin);
        $base = ['marca' => 'X', 'modelo' => 'Y', 'ano_fabricacao' => 2023, 'ano_modelo' => 2023, 'estado_inicial' => 'bom', 'km_inicial' => 1000, 'valor_aquisicao' => 99000];
        $this->onix = app(VeiculoService::class)->criar($base + ['nome' => 'Onix', 'placa' => 'BRA2E19', 'chassi' => '9BGKS48U0PG123456']);
        $this->strada = app(VeiculoService::class)->criar($base + ['nome' => 'Strada', 'placa' => 'ABC1234']);
        auth()->logout();
    }

    private function usuario(string $perfil, string $login, string $cpf, array $extra = []): Usuario
    {
        return Usuario::create(array_merge([
            'nome' => "Usuário {$login}", 'login' => $login, 'email' => "{$login}@teste.local", 'cpf' => $cpf,
            'senha' => 'segredo123', 'perfil_id' => Perfil::where('codigo', $perfil)->value('id'), 'ativo' => true,
        ], $extra))->fresh();
    }

    public function test_api_exige_token_e_escopo_e_responde_no_envelope(): void
    {
        $this->getJson('/api/integracao/v1/veiculos')->assertStatus(401)
            ->assertJson(['sucesso' => false, 'erro' => ['codigo' => 'nao_autenticado']]);
        $this->withToken('invalido')->getJson('/api/integracao/v1/veiculos')->assertStatus(401);

        $token = TokenIntegracao::criarPara('emissao-os');

        $resposta = $this->withToken($token)->getJson('/api/integracao/v1/veiculos')->assertOk()
            ->assertJson(['sucesso' => true, 'meta' => ['total' => 2]]);
        $primeiro = $resposta->json('dados.0');
        $this->assertSame('Onix', $primeiro['nome']);
        $this->assertSame('BRA-2E19', $primeiro['placa_formatada']);
        // Dados sensíveis não saem.
        $this->assertArrayNotHasKey('valor_aquisicao', $primeiro);
        $this->assertArrayNotHasKey('chassi', $primeiro);
        $this->assertNotNull(TokenIntegracao::first()->ultimo_uso_em);

        // Agenda exige escopo.
        $this->withToken($token)->getJson('/api/integracao/v1/alocacoes?de=2026-01-01&ate=2026-01-31')->assertStatus(403)
            ->assertJson(['erro' => ['codigo' => 'permissao_negada']]);

        // Rota inexistente e método errado também no envelope.
        $this->withToken($token)->getJson('/api/integracao/v1/nada')->assertStatus(404)->assertJson(['erro' => ['codigo' => 'nao_encontrado']]);
        $this->withToken($token)->postJson('/api/integracao/v1/veiculos')->assertStatus(405)->assertJson(['sucesso' => false]);
    }

    public function test_disponiveis_considera_conflito_situacao_e_validacao(): void
    {
        $token = TokenIntegracao::criarPara('emissao-os', ['alocacoes']);
        $de = now()->addDay()->setTime(8, 0);
        $ate = now()->addDay()->setTime(18, 0);

        // Onix alocado no meio do intervalo.
        Alocacao::create([
            'veiculo_id' => $this->onix->id, 'motorista_id' => $this->gestor->id, 'solicitante_id' => $this->gestor->id,
            'objetivo' => 'Instalação', 'saida_prevista' => $de->copy()->addHours(2), 'retorno_previsto' => $de->copy()->addHours(4), 'situacao' => 'aprovada',
        ]);

        $url = '/api/integracao/v1/veiculos/disponiveis?de='.$de->format('Y-m-d\TH:i').'&ate='.$ate->format('Y-m-d\TH:i');
        $disponiveis = $this->withToken($token)->getJson($url)->assertOk()->json('dados');
        $this->assertSame(['Strada'], array_column($disponiveis, 'nome'));

        // Strada indisponível: some também.
        $this->actingAs($this->admin);
        app(VeiculoService::class)->mudarSituacao($this->strada, SituacaoVeiculo::Indisponivel);
        auth()->logout();
        $this->withToken($token)->getJson($url)->assertOk()->assertJson(['meta' => ['total' => 0]]);

        $this->withToken($token)->getJson('/api/integracao/v1/veiculos/disponiveis?de=2026-10-02&ate=2026-10-01')->assertStatus(422)
            ->assertJson(['erro' => ['codigo' => 'validacao']]);

        // Agenda com escopo: traz a alocação com o motorista.
        $agenda = $this->withToken($token)->getJson('/api/integracao/v1/alocacoes?de='.$de->toDateString().'&ate='.$de->toDateString())->assertOk()->json('dados');
        $this->assertCount(1, $agenda);
        $this->assertSame('Usuário gestor', $agenda[0]['motorista']);
    }

    public function test_comando_de_token_cria_lista_e_revoga(): void
    {
        $this->artisan('integracao:token criar emissao-os --escopos=alocacoes')->assertSuccessful();
        $this->assertTrue(TokenIntegracao::where('nome', 'emissao-os')->first()->temEscopo('alocacoes'));
        $this->artisan('integracao:token criar outro --escopos=inexistente')->assertFailed();
        $this->artisan('integracao:token listar')->assertSuccessful();
        $this->artisan('integracao:token revogar emissao-os')->assertSuccessful();
        $this->assertSame(0, TokenIntegracao::count());
    }

    public function test_sincroniza_colaboradores_do_rh_e_falha_em_silencio(): void
    {
        config(['services.gestao_pessoas.url' => 'https://pessoas.teste', 'services.gestao_pessoas.token' => 'abc']);

        $vinculado = $this->usuario('geral', 'm1', '15350946056', ['colaborador_externo_id' => 10, 'pode_dirigir' => true]);
        $desligado = $this->usuario('geral', 'm2', '39053344705', ['colaborador_externo_id' => 11]);
        $comCarro = $this->usuario('geral', 'm3', '12345678909', ['colaborador_externo_id' => 12, 'pode_dirigir' => true]);
        Alocacao::create([
            'veiculo_id' => $this->onix->id, 'motorista_id' => $comCarro->id, 'solicitante_id' => $comCarro->id,
            'objetivo' => 'x', 'saida_prevista' => now(), 'retorno_previsto' => now()->addHours(3), 'situacao' => 'em_uso',
        ]);

        // Um fake só, controlado pela variável: um segundo Http::fake não
        // substituiria o primeiro.
        $foraDoAr = false;
        Http::fake(function () use (&$foraDoAr) {
            return $foraDoAr ? Http::response('erro', 500) : Http::response(['sucesso' => true, 'dados' => [
                ['id' => 10, 'nome' => 'Maria da Silva', 'setor' => ['id' => 1, 'nome' => 'Instalação'], 'cargo' => 'Técnico', 'situacao' => 'ativo'],
                ['id' => 11, 'nome' => 'João Souza', 'setor' => null, 'cargo' => null, 'situacao' => 'desligado'],
                ['id' => 12, 'nome' => 'Pedro Lima', 'setor' => null, 'cargo' => null, 'situacao' => 'desligado'],
            ]]);
        });

        $this->actingAs($this->admin)->post(route('usuarios.sincronizar-rh'))->assertSessionHas('aviso');

        $vinculado->refresh();
        $this->assertSame('Maria da Silva', $vinculado->nome);
        $this->assertSame('Instalação', $vinculado->setor->nome);
        $this->assertSame('Técnico', $vinculado->cargo->nome);
        $this->assertFalse($desligado->fresh()->ativo);
        $this->assertTrue($comCarro->fresh()->ativo, 'com carro não é inativado');
        $this->assertTrue(Notificacao::where('usuario_id', $this->admin->id)->where('tipo', "rh_desligado_{$comCarro->id}")->exists());

        // RH fora do ar: nada muda e a tela avisa.
        $foraDoAr = true;
        $this->actingAs($this->admin)->post(route('usuarios.sincronizar-rh'))->assertSessionHas('erro');
        $this->artisan('integracao:sincronizar-colaboradores')->assertFailed();

        // Só admin sincroniza.
        $this->actingAs($this->gestor)->post(route('usuarios.sincronizar-rh'))->assertForbidden();
    }

    public function test_formulario_de_usuario_lista_fichas_do_rh_e_sobrevive_ao_rh_fora(): void
    {
        config(['services.gestao_pessoas.url' => 'https://pessoas.teste', 'services.gestao_pessoas.token' => 'abc']);
        $foraDoAr = false;
        // Closure por referência (arrow fn capturaria o valor de agora).
        Http::fake(function () use (&$foraDoAr) {
            return $foraDoAr ? Http::response('erro', 500) : Http::response(['sucesso' => true, 'dados' => [
                ['id' => 10, 'nome' => 'Maria da Silva', 'setor' => ['id' => 1, 'nome' => 'Instalação'], 'situacao' => 'ativo'],
            ]]);
        });

        $this->actingAs($this->admin)->get(route('usuarios.create'))->assertOk()->assertSee('Maria da Silva');

        cache()->flush();
        $foraDoAr = true;
        $this->actingAs($this->admin)->get(route('usuarios.create'))->assertOk()->assertSee('Gestão de Pessoas indisponível');
    }

    public function test_pwa_manifest_e_service_worker(): void
    {
        $manifest = json_decode((string) file_get_contents(public_path('manifest.json')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('/painel', $manifest['start_url']);
        foreach ($manifest['icons'] as $icone) {
            $this->assertFileExists(public_path(ltrim($icone['src'], '/')));
        }
        $this->assertFileExists(public_path('sw.js'));

        $this->actingAs($this->admin)->get(route('painel'))->assertOk()->assertSee('rel="manifest"', false);
    }
}
