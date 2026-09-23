<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SituacaoAlocacao;
use App\Enums\SituacaoVeiculo;
use App\Models\Alocacao;
use App\Models\Checagem;
use App\Models\Manutencao;
use App\Models\Perfil;
use App\Models\Usuario;
use App\Models\Veiculo;
use App\Services\VeiculoService;
use Database\Seeders\PerfilSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Regressões da revisão de 23/09/2026 (fases 0 a 4). */
class RevisaoBugsTest extends TestCase
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

    private function fazerChecagem(Alocacao $alocacao, Usuario $motorista, int $km): Checagem
    {
        $this->actingAs($motorista)->post(route('alocacoes.checagem', $alocacao))->assertRedirect();
        $checagem = $alocacao->checagens()->latest('id')->firstOrFail();
        foreach ($checagem->itens as $item) {
            $this->actingAs($motorista)->postJson(route('checagens.item', [$checagem, $item]), [
                'foto' => UploadedFile::fake()->image('f.jpg'), 'situacao' => 'conforme',
            ])->assertOk();
        }
        $this->actingAs($motorista)->post(route('checagens.concluir', $checagem), [
            'km_informado' => $km, 'nivel_combustivel' => 'meio', 'estado_geral' => 'bom',
        ])->assertSessionHas('sucesso');

        return $checagem;
    }

    private function hora(int $horas): string
    {
        return now()->addHours($horas)->format('Y-m-d H:i');
    }

    public function test_fuso_da_aplicacao_e_brasilia(): void
    {
        $this->assertSame('America/Sao_Paulo', config('app.timezone'));
    }

    public function test_gestor_nao_promove_a_admin_nem_tira_da_cadeia_nem_edita_admin(): void
    {
        $base = [
            'nome' => 'Novo', 'login' => 'novo', 'email' => 'novo@t.local', 'cpf' => '12345678909',
            'senha' => 'senhaForte1', 'perfil_id' => Perfil::where('codigo', 'admin')->value('id'),
        ];

        $this->actingAs($this->gestor)->from(route('usuarios.create'))->post(route('usuarios.store'), $base)
            ->assertSessionHasErrors('perfil_id');

        $this->actingAs($this->gestor)->from(route('usuarios.create'))->post(route('usuarios.store'), array_merge($base, [
            'perfil_id' => Perfil::where('codigo', 'geral')->value('id'), 'gestor_id' => $this->admin->id,
        ]))->assertSessionHasErrors('gestor_id');

        // Admin pendurado no gestor continua fora do alcance dele.
        $this->admin->update(['gestor_id' => null]);
        $outroAdmin = $this->usuario('admin', 'adm2', '98765432100', ['gestor_id' => $this->gestor->id]);
        $this->actingAs($this->gestor)->get(route('usuarios.edit', $outroAdmin))->assertForbidden();

        // Ciclo: gestor não pode ter como gestor alguém da própria equipe.
        $this->actingAs($this->admin)->from(route('usuarios.edit', $this->gestor))->put(route('usuarios.update', $this->gestor), [
            'nome' => $this->gestor->nome, 'login' => 'gestor', 'email' => 'gestor@teste.local', 'cpf' => '52998224725',
            'perfil_id' => $this->gestor->perfil_id, 'gestor_id' => $this->m1->id,
        ])->assertSessionHasErrors('gestor_id');

        $this->actingAs($this->admin)->delete(route('usuarios.destroy', $this->admin))->assertForbidden();
    }

    public function test_saida_bloqueada_com_veiculo_em_manutencao_ou_ainda_em_uso(): void
    {
        // m1 sai e atrasa; a alocação de m2 começa depois do retorno previsto de m1.
        $a1 = $this->alocar($this->gestor, $this->m1, $this->hora(1), $this->hora(2));
        $this->fazerChecagem($a1, $this->m1, 1000);
        $a2 = $this->alocar($this->gestor, $this->m2, $this->hora(3), $this->hora(5));
        $this->travel(3)->hours();

        $this->actingAs($this->m2)->post(route('alocacoes.checagem', $a2))->assertSessionHas('erro');
        $this->assertSame(0, $a2->checagens()->count());

        // m1 devolve: como a2 sai hoje, o veículo fica reservado (não disponível).
        $this->fazerChecagem($a1, $this->m1, 1100);
        $this->assertSame(SituacaoVeiculo::Reservado, $this->veiculo->fresh()->situacao);

        // Manutenção iniciada impede a saída de a2.
        $this->actingAs($this->admin);
        app(VeiculoService::class)->mudarSituacao($this->veiculo->fresh(), SituacaoVeiculo::EmManutencao, 'manutencao');
        $this->actingAs($this->m2)->post(route('alocacoes.checagem', $a2))->assertSessionHas('erro');
    }

    public function test_cancelar_alocacao_futura_nao_libera_veiculo_em_uso(): void
    {
        $a1 = $this->alocar($this->gestor, $this->m1, $this->hora(1), $this->hora(3));
        $this->fazerChecagem($a1, $this->m1, 1000);
        $futura = $this->alocar($this->gestor, $this->m2, now()->addDays(2)->format('Y-m-d H:i'), now()->addDays(2)->addHours(2)->format('Y-m-d H:i'));

        $this->actingAs($this->gestor)->patch(route('alocacoes.cancelar', $futura), ['motivo' => 'x'])->assertSessionHas('sucesso');

        $this->assertSame(SituacaoVeiculo::EmUso, $this->veiculo->fresh()->situacao);
        $this->assertSame(SituacaoAlocacao::EmUso, $a1->fresh()->situacao);
    }

    public function test_situacao_manual_nao_mexe_em_veiculo_em_uso(): void
    {
        $a1 = $this->alocar($this->gestor, $this->m1, $this->hora(1), $this->hora(3));
        $this->fazerChecagem($a1, $this->m1, 1000);

        $this->actingAs($this->gestor)->patch(route('veiculos.situacao', $this->veiculo), ['situacao' => 'disponivel', 'observacao' => 'x'])
            ->assertSessionHas('erro');
        $this->assertSame(SituacaoVeiculo::EmUso, $this->veiculo->fresh()->situacao);
    }

    public function test_trocar_resposta_do_item_sem_nova_foto(): void
    {
        $a1 = $this->alocar($this->gestor, $this->m1, $this->hora(1), $this->hora(3));
        $this->actingAs($this->m1)->post(route('alocacoes.checagem', $a1));
        $checagem = $a1->checagens()->firstOrFail();
        $item = $checagem->itens->first();

        // Sem foto nenhuma ainda: recusa.
        $this->actingAs($this->m1)->postJson(route('checagens.item', [$checagem, $item]), ['situacao' => 'conforme'])
            ->assertStatus(422);

        $this->actingAs($this->m1)->postJson(route('checagens.item', [$checagem, $item]), ['foto' => UploadedFile::fake()->image('f.jpg'), 'situacao' => 'conforme'])->assertOk();
        $this->actingAs($this->m1)->postJson(route('checagens.item', [$checagem, $item]), ['situacao' => 'anomalia', 'observacao' => 'Amassado'])
            ->assertOk()->assertJson(['situacao' => 'anomalia']);

        $this->assertSame(1, $item->fotos()->count());
    }

    public function test_saida_fora_da_janela_e_rascunho_de_alocacao_cancelada(): void
    {
        $futura = $this->alocar($this->gestor, $this->m1, now()->addDays(3)->format('Y-m-d H:i'), now()->addDays(3)->addHours(2)->format('Y-m-d H:i'));
        $this->actingAs($this->m1)->post(route('alocacoes.checagem', $futura))->assertSessionHas('erro');

        // Rascunho aberto; a alocação é cancelada; concluir não ressuscita nada.
        $hoje = $this->alocar($this->gestor, $this->m2, $this->hora(1), $this->hora(3));
        $this->actingAs($this->m2)->post(route('alocacoes.checagem', $hoje))->assertRedirect();
        $rascunho = $hoje->checagens()->firstOrFail();
        $this->actingAs($this->gestor)->patch(route('alocacoes.cancelar', $hoje), ['motivo' => 'x'])->assertSessionHas('sucesso');

        $this->assertNull(Checagem::find($rascunho->id), 'rascunho descartado no cancelamento');
        $this->assertSame(SituacaoAlocacao::Cancelada, $hoje->fresh()->situacao);
        $this->assertSame(SituacaoVeiculo::Disponivel, $this->veiculo->fresh()->situacao);
    }

    public function test_inativar_motorista_com_carro_e_encerramento_pelo_admin(): void
    {
        $a1 = $this->alocar($this->gestor, $this->m1, $this->hora(1), $this->hora(3));
        $this->fazerChecagem($a1, $this->m1, 1000);

        $this->actingAs($this->admin)->patch(route('usuarios.toggle-ativo', $this->m1))->assertSessionHas('erro');
        $this->assertTrue($this->m1->fresh()->ativo);

        $this->actingAs($this->gestor)->patch(route('alocacoes.encerrar', $a1), ['km_retorno' => 1100, 'motivo' => 'x'])->assertForbidden();
        $this->actingAs($this->admin)->patch(route('alocacoes.encerrar', $a1), ['km_retorno' => 1100, 'motivo' => 'Celular perdido'])->assertSessionHas('sucesso');

        $this->assertSame(SituacaoAlocacao::Concluida, $a1->fresh()->situacao);
        $this->assertSame(1100, $this->veiculo->fresh()->km_atual);
        $this->assertSame(SituacaoVeiculo::Disponivel, $this->veiculo->fresh()->situacao);

        // Agora pode inativar; a sessão dele cai na próxima requisição.
        $this->actingAs($this->admin)->patch(route('usuarios.toggle-ativo', $this->m1))->assertSessionHas('sucesso');
        $this->actingAs($this->m1->fresh())->get(route('painel'))->assertRedirect(route('login'));
    }

    public function test_bloqueio_pedido_com_carro_na_rua_vale_no_retorno(): void
    {
        $a1 = $this->alocar($this->gestor, $this->m1, $this->hora(1), $this->hora(3));
        $this->fazerChecagem($a1, $this->m1, 1000);

        $this->actingAs($this->gestor)->post(route('manutencoes.store'), [
            'veiculo_id' => $this->veiculo->id, 'tipo' => 'imediata', 'nome' => 'Freio', 'bloquear_veiculo' => 1,
        ])->assertRedirect();

        $this->fazerChecagem($a1, $this->m1, 1080);
        $this->assertSame(SituacaoVeiculo::Indisponivel, $this->veiculo->fresh()->situacao);
    }

    public function test_manutencao_respeita_baixado_e_indisponivel_manual(): void
    {
        $this->actingAs($this->gestor)->post(route('manutencoes.store'), ['veiculo_id' => $this->veiculo->id, 'tipo' => 'planejada', 'nome' => 'Revisão']);
        $m = Manutencao::firstOrFail();

        $this->actingAs($this->gestor)->patch(route('veiculos.situacao', $this->veiculo), ['situacao' => 'indisponivel', 'observacao' => 'Documento pendente']);
        $this->actingAs($this->gestor)->patch(route('manutencoes.iniciar', $m))->assertSessionHas('sucesso');
        $this->actingAs($this->gestor)->patch(route('manutencoes.concluir', $m), ['preco_final' => '100'])->assertSessionHas('sucesso');
        $this->assertSame(SituacaoVeiculo::Indisponivel, $this->veiculo->fresh()->situacao, 'volta como o gestor deixou');

        $this->actingAs($this->gestor)->post(route('manutencoes.store'), ['veiculo_id' => $this->veiculo->id, 'tipo' => 'planejada', 'nome' => 'Outra']);
        $m2 = Manutencao::latest('id')->firstOrFail();
        $this->actingAs($this->gestor)->patch(route('veiculos.situacao', $this->veiculo), ['situacao' => 'baixado', 'observacao' => 'Vendido']);
        $this->actingAs($this->gestor)->patch(route('manutencoes.iniciar', $m2))->assertSessionHas('erro');
        $this->assertSame(SituacaoVeiculo::Baixado, $this->veiculo->fresh()->situacao);
    }

    public function test_excluir_veiculo_com_historico_nao_quebra_relatorios(): void
    {
        $this->actingAs($this->gestor)->post(route('manutencoes.store'), ['veiculo_id' => $this->veiculo->id, 'tipo' => 'planejada', 'nome' => 'Revisão']);
        $m = Manutencao::firstOrFail();
        $this->actingAs($this->gestor)->patch(route('manutencoes.iniciar', $m));
        $this->actingAs($this->gestor)->patch(route('manutencoes.concluir', $m), ['preco_final' => '300']);

        $this->actingAs($this->admin)->patch(route('veiculos.situacao', $this->veiculo), ['situacao' => 'baixado', 'observacao' => 'Vendido']);
        $this->actingAs($this->admin)->delete(route('veiculos.destroy', $this->veiculo))->assertSessionHas('sucesso');

        $this->actingAs($this->admin)->get(route('manutencoes.index'))->assertOk()->assertSee('Onix');
        $this->actingAs($this->admin)->get(route('relatorios.custos'))->assertOk();
        $this->actingAs($this->admin)->get(route('relatorios.custos.excel'))->assertOk();
        $this->actingAs($this->admin)->get(route('painel'))->assertOk();
    }

    public function test_telas_de_checagem_com_itens_respondidos_nao_quebram(): void
    {
        $a1 = $this->alocar($this->gestor, $this->m1, $this->hora(1), $this->hora(3));
        $this->actingAs($this->m1)->post(route('alocacoes.checagem', $a1));
        $checagem = $a1->checagens()->firstOrFail();
        foreach ($checagem->itens->take(3) as $item) {
            $this->actingAs($this->m1)->postJson(route('checagens.item', [$checagem, $item]), ['foto' => UploadedFile::fake()->image('f.jpg'), 'situacao' => 'conforme'])->assertOk();
        }

        $this->actingAs($this->m1)->get(route('checagens.editar', $checagem))->assertOk()->assertSee('Faltam 10');
        $this->actingAs($this->m1)->get(route('alocacoes.show', $a1))->assertOk();
        $this->actingAs($this->gestor)->get(route('alocacoes.agenda', ['inicio' => 'lixo']))->assertOk();
    }

    public function test_admin_nao_ve_acoes_impossiveis(): void
    {
        $a1 = $this->alocar($this->gestor, $this->m1, $this->hora(1), $this->hora(3));

        $this->assertFalse($this->admin->can('checar', $a1), 'checagem é só do motorista');
        $this->assertFalse($this->admin->can('aprovar', $a1), 'já está aprovada');
        $this->assertTrue($this->admin->can('view', $a1));
        $this->assertTrue($this->admin->can('cancelar', $a1));
    }
}
