<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SituacaoAlocacao;
use App\Enums\SituacaoVeiculo;
use App\Models\Alocacao;
use App\Models\Checagem;
use App\Models\Fornecedor;
use App\Models\Manutencao;
use App\Models\Perfil;
use App\Models\PlanoManutencao;
use App\Models\TokenIntegracao;
use App\Models\Usuario;
use App\Models\Veiculo;
use App\Services\VeiculoService;
use App\Support\Numero;
use Database\Seeders\PerfilSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** Regressões da revisão geral de 23/09/2026 (sistema inteiro, fases 0 a 5). */
class RevisaoGeralTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $admin;

    private Usuario $gestor;

    private Usuario $m1;

    private Usuario $m2;

    private Veiculo $veiculo;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(PerfilSeeder::class);

        $this->admin = $this->usuario('admin', 'admin', '11144477735', ['pode_dirigir' => true]);
        $this->gestor = $this->usuario('gestor', 'gestor', '52998224725', ['gestor_id' => $this->admin->id, 'pode_dirigir' => true]);
        $this->m1 = $this->usuario('geral', 'm1', '15350946056', ['gestor_id' => $this->gestor->id, 'pode_dirigir' => true]);
        $this->m2 = $this->usuario('geral', 'm2', '39053344705', ['gestor_id' => $this->gestor->id, 'pode_dirigir' => true]);

        $this->actingAs($this->admin);
        $this->veiculo = app(VeiculoService::class)->criar([
            'nome' => 'Onix', 'placa' => 'BRA2E19', 'marca' => 'X', 'modelo' => 'Y',
            'ano_fabricacao' => 2023, 'ano_modelo' => 2023, 'estado_inicial' => 'bom', 'km_inicial' => 1000,
        ]);
        auth()->logout();
    }

    private function usuario(string $perfil, string $login, string $cpf, array $extra = []): Usuario
    {
        return Usuario::create(array_merge([
            'nome' => "Usuário {$login}", 'login' => $login, 'email' => "{$login}@teste.local", 'cpf' => $cpf,
            'senha' => 'segredo123', 'perfil_id' => Perfil::where('codigo', $perfil)->value('id'), 'ativo' => true,
        ], $extra))->fresh();
    }

    private function alocar(Usuario $quem, Usuario $motorista, string $saida, string $retorno): Alocacao
    {
        $this->actingAs($quem)->post(route('alocacoes.store'), [
            'veiculo_id' => $this->veiculo->id, 'motorista_id' => $motorista->id, 'objetivo' => 'x',
            'saida_prevista' => $saida, 'retorno_previsto' => $retorno,
        ])->assertSessionHas('sucesso');

        return Alocacao::latest('id')->firstOrFail();
    }

    /** Abre a checagem, responde todos os itens e tenta concluir com o km dado. */
    private function tentarChecagem(Alocacao $alocacao, Usuario $motorista, int $km): TestResponse
    {
        $this->actingAs($motorista)->post(route('alocacoes.checagem', $alocacao))->assertRedirect();
        $checagem = $alocacao->checagens()->latest('id')->firstOrFail();
        foreach ($checagem->itens as $item) {
            $this->actingAs($motorista)->postJson(route('checagens.item', [$checagem, $item]), [
                'foto' => UploadedFile::fake()->image('f.jpg'), 'situacao' => 'conforme',
            ])->assertOk();
        }

        return $this->actingAs($motorista)->post(route('checagens.concluir', $checagem), [
            'km_informado' => $km, 'nivel_combustivel' => 'meio', 'estado_geral' => 'bom',
        ]);
    }

    private function hora(int $horas): string
    {
        return now()->addHours($horas)->format('Y-m-d H:i');
    }

    public function test_saida_antecipada_nao_atropela_reserva_de_outro_motorista(): void
    {
        $this->travelTo(now()->addDay()->setTime(7, 0));
        $a = $this->alocar($this->gestor, $this->m1, now()->setTime(8, 0)->format('Y-m-d H:i'), now()->setTime(10, 0)->format('Y-m-d H:i'));
        $b = $this->alocar($this->gestor, $this->m2, now()->setTime(14, 0)->format('Y-m-d H:i'), now()->setTime(18, 0)->format('Y-m-d H:i'));

        // Às 09h o motorista da tarde tenta levar o carro que está com a reserva da manhã.
        $this->travelTo(now()->setTime(9, 0));
        $this->actingAs($this->m2)->post(route('alocacoes.checagem', $b))->assertSessionHas('erro');
        $this->assertSame(0, Checagem::count());

        // O da manhã sai normalmente.
        $this->tentarChecagem($a, $this->m1, 1010)->assertSessionHas('sucesso');
        $this->assertSame(SituacaoAlocacao::EmUso, $a->fresh()->situacao);
    }

    public function test_km_absurdo_na_saida_e_recusado_e_gestor_corrige_km(): void
    {
        $a = $this->alocar($this->gestor, $this->m1, $this->hora(1), $this->hora(4));

        // 10100 em vez de 1010 (dígito a mais): recusado, nada muda.
        $this->tentarChecagem($a, $this->m1, 10100)->assertSessionHas('erro');
        $this->assertSame(1000, $this->veiculo->fresh()->km_atual);
        $this->assertSame(SituacaoAlocacao::Aprovada, $a->fresh()->situacao);

        $this->actingAs($this->m1)->post(route('checagens.concluir', $a->checagens()->firstOrFail()), [
            'km_informado' => 1010, 'nivel_combustivel' => 'meio', 'estado_geral' => 'bom',
        ])->assertSessionHas('sucesso');

        // Com o carro na rua, o km não é ajustado à mão (travaria o retorno).
        $this->actingAs($this->gestor)->from(route('veiculos.show', $this->veiculo))
            ->patch(route('veiculos.estado', $this->veiculo), ['estado_atual' => 'bom', 'km_atual' => 900, 'observacao' => 'x'])
            ->assertSessionHas('erro');
        $this->assertSame('bom', $this->veiculo->fresh()->estado_atual->value, 'estado e km na mesma transação');
    }

    public function test_valor_digitado_com_ponto_de_milhar(): void
    {
        $this->assertSame('1500', Numero::dePtBr('1.500'));
        $this->assertSame('45000', Numero::dePtBr('45.000'));
        $this->assertSame('1234.56', Numero::dePtBr('1.234,56'));
        $this->assertSame('1234567', Numero::dePtBr('1.234.567'));
        $this->assertSame('1500.5', Numero::dePtBr('1500.5'));
        $this->assertSame('10', Numero::dePtBr('R$ 10'));
        $this->assertNull(Numero::dePtBr(''));
    }

    public function test_gestor_nao_da_perfil_financeiro_e_gestor_direto_precisa_poder_aprovar(): void
    {
        $dados = [
            'nome' => $this->m1->nome, 'login' => 'm1', 'email' => 'm1@teste.local', 'cpf' => '15350946056',
            'perfil_id' => Perfil::where('codigo', 'financeiro')->value('id'), 'gestor_id' => $this->gestor->id, 'ativo' => 1,
        ];

        $this->actingAs($this->gestor)->from(route('usuarios.edit', $this->m1))->put(route('usuarios.update', $this->m1), $dados)
            ->assertSessionHasErrors('perfil_id');

        // Um "geral" não pode ser gestor direto de ninguém.
        $this->actingAs($this->admin)->from(route('usuarios.edit', $this->m1))->put(route('usuarios.update', $this->m1), array_merge($dados, [
            'perfil_id' => $this->m1->perfil_id, 'gestor_id' => $this->m2->id,
        ]))->assertSessionHasErrors('gestor_id');
    }

    public function test_inscricao_de_push_so_aceita_servico_de_push(): void
    {
        $chaves = ['keys' => ['p256dh' => 'abc', 'auth' => 'def']];

        foreach (['http://169.254.169.254/latest/meta-data', 'https://mysql:3306/', 'https://fcm.googleapis.com.evil.io/x'] as $endpoint) {
            $this->actingAs($this->m1)->postJson(route('webpush.inscrever'), ['endpoint' => $endpoint] + $chaves)->assertStatus(422);
        }

        $this->actingAs($this->m1)->postJson(route('webpush.inscrever'), ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc'] + $chaves)->assertOk();
        $this->actingAs($this->m1)->postJson(route('webpush.inscrever'), ['endpoint' => 'https://wns2-by3p.notify.windows.com/w/?token=x'] + $chaves)->assertOk();
    }

    public function test_api_de_agenda_inclui_carro_em_uso_com_retorno_atrasado(): void
    {
        $a = $this->alocar($this->gestor, $this->m1, $this->hora(1), $this->hora(3));
        $this->tentarChecagem($a, $this->m1, 1010)->assertSessionHas('sucesso');

        $this->travelTo(now()->addDays(2));
        $token = TokenIntegracao::criarPara('emissao-os', ['alocacoes']);
        $hoje = now()->toDateString();

        $agenda = $this->withToken($token)->getJson("/api/integracao/v1/alocacoes?de={$hoje}&ate={$hoje}")->assertOk()->json('dados');
        $this->assertCount(1, $agenda);
        $this->assertSame('em_uso', $agenda[0]['situacao']);
    }

    public function test_solicitacao_nao_aprovada_expira_e_periodo_passado_e_recusado(): void
    {
        $pedido = $this->alocar($this->m1, $this->m1, $this->hora(1), $this->hora(3));
        $this->assertSame(SituacaoAlocacao::Solicitada, $pedido->situacao);

        $this->travelTo(now()->addHours(4));
        $this->artisan('alocacoes:sincronizar')->assertSuccessful();
        $this->assertSame(SituacaoAlocacao::Cancelada, $pedido->fresh()->situacao);

        $this->actingAs($this->gestor)->post(route('alocacoes.store'), [
            'veiculo_id' => $this->veiculo->id, 'motorista_id' => $this->m1->id, 'objetivo' => 'x',
            'saida_prevista' => $this->hora(-5), 'retorno_previsto' => $this->hora(-1),
        ])->assertSessionHas('erro');
    }

    public function test_indisponivel_manual_sobrevive_a_manutencao_com_bloqueio(): void
    {
        $this->actingAs($this->gestor)->patch(route('veiculos.situacao', $this->veiculo), ['situacao' => 'indisponivel', 'observacao' => 'Documento pendente'])
            ->assertSessionHas('sucesso');

        $this->actingAs($this->gestor)->post(route('manutencoes.store'), [
            'veiculo_id' => $this->veiculo->id, 'tipo' => 'imediata', 'nome' => 'Freio', 'bloquear_veiculo' => 1,
        ])->assertRedirect();
        $m = Manutencao::firstOrFail();
        $this->actingAs($this->gestor)->patch(route('manutencoes.cancelar', $m), ['motivo' => 'Desistiu'])->assertSessionHas('sucesso');
        $this->assertSame(SituacaoVeiculo::Indisponivel, $this->veiculo->fresh()->situacao, 'volta como o gestor deixou');

        // Liberado à mão; depois, bloqueio de manutenção não se desfaz à mão.
        $this->actingAs($this->gestor)->patch(route('veiculos.situacao', $this->veiculo), ['situacao' => 'disponivel', 'observacao' => 'ok'])->assertSessionHas('sucesso');
        $this->actingAs($this->gestor)->post(route('manutencoes.store'), [
            'veiculo_id' => $this->veiculo->id, 'tipo' => 'imediata', 'nome' => 'Pneu', 'bloquear_veiculo' => 1,
        ])->assertRedirect();
        $this->assertSame(SituacaoVeiculo::Indisponivel, $this->veiculo->fresh()->situacao);
        $this->actingAs($this->gestor)->patch(route('veiculos.situacao', $this->veiculo), ['situacao' => 'disponivel', 'observacao' => 'x'])->assertSessionHas('erro');

        // E, cancelada, o veículo volta a disponível (o bloqueio era da manutenção).
        $this->actingAs($this->gestor)->patch(route('manutencoes.cancelar', Manutencao::latest('id')->firstOrFail()), ['motivo' => 'x']);
        $this->assertSame(SituacaoVeiculo::Disponivel, $this->veiculo->fresh()->situacao);
    }

    public function test_preventiva_aberta_a_mao_liga_ao_plano_vencido(): void
    {
        $plano = PlanoManutencao::create(['veiculo_id' => $this->veiculo->id, 'nome' => 'Revisão', 'intervalo_km' => 1000, 'ultimo_km' => 0, 'ativo' => true]);

        $this->actingAs($this->gestor)->post(route('manutencoes.store'), [
            'veiculo_id' => $this->veiculo->id, 'tipo' => 'preventiva', 'nome' => 'Revisão 1.000 km',
        ])->assertRedirect();
        $this->assertSame($plano->id, Manutencao::firstOrFail()->plano_manutencao_id);

        $this->artisan('manutencoes:verificar-planos')->assertSuccessful();
        $this->assertSame(1, Manutencao::count(), 'a rotina não abre outra igual');

        // Cancelada: não reabre no dia seguinte.
        $this->actingAs($this->gestor)->patch(route('manutencoes.cancelar', Manutencao::firstOrFail()), ['motivo' => 'Adiada']);
        $this->travelTo(now()->addDay());
        $this->artisan('manutencoes:verificar-planos')->assertSuccessful();
        $this->assertSame(1, Manutencao::count());
    }

    public function test_so_admin_exclui_fornecedor_e_placa_de_excluido_pode_ser_reusada(): void
    {
        $fornecedor = Fornecedor::create(['razao_social' => 'Oficina X', 'ativo' => true]);
        $this->actingAs($this->gestor)->delete(route('fornecedores.destroy', $fornecedor))->assertForbidden();
        $this->actingAs($this->admin)->delete(route('fornecedores.destroy', $fornecedor))->assertSessionHas('sucesso');

        $this->actingAs($this->admin)->patch(route('veiculos.situacao', $this->veiculo), ['situacao' => 'baixado', 'observacao' => 'Cadastro errado']);
        $this->actingAs($this->admin)->delete(route('veiculos.destroy', $this->veiculo))->assertSessionHas('sucesso');

        $this->actingAs($this->admin)->post(route('veiculos.store'), [
            'nome' => 'Onix novo', 'placa' => 'BRA-2E19', 'marca' => 'Chevrolet', 'modelo' => 'Onix',
            'ano_fabricacao' => 2023, 'ano_modelo' => 2024, 'estado_inicial' => 'bom', 'km_inicial' => 0,
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, Veiculo::where('placa', 'BRA2E19')->count());
    }

    public function test_historico_de_checagens_e_de_quem_cuida_da_frota(): void
    {
        $this->actingAs($this->m1)->get(route('checagens.historico', $this->veiculo))->assertForbidden();
        $this->actingAs($this->gestor)->get(route('checagens.historico', $this->veiculo))->assertOk();
    }

    public function test_erro_de_validacao_da_checagem_volta_em_json(): void
    {
        $a = $this->alocar($this->gestor, $this->m1, $this->hora(1), $this->hora(4));
        $this->actingAs($this->m1)->post(route('alocacoes.checagem', $a));
        $checagem = $a->checagens()->firstOrFail();

        $this->actingAs($this->m1)->post(route('checagens.item', [$checagem, $checagem->itens->first()]), [
            'foto' => UploadedFile::fake()->create('foto.heic', 100, 'image/heic'), 'situacao' => 'conforme',
        ], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonStructure(['errors' => ['foto']]);
    }

    public function test_redefinir_senha_derruba_as_outras_sessoes(): void
    {
        // Sessão do motorista aberta (hash da senha guardado nela).
        $this->actingAs($this->m1)->get(route('painel'))->assertOk();

        $this->m1->forceFill(['senha' => 'outraSenha1'])->save();

        $this->actingAs($this->m1->fresh())->get(route('painel'))->assertRedirect(route('login'));
    }
}
