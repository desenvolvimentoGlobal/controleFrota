<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SituacaoCondicao;
use App\Enums\SituacaoManutencao;
use App\Enums\SituacaoVeiculo;
use App\Models\Fornecedor;
use App\Models\Manutencao;
use App\Models\Notificacao;
use App\Models\Perfil;
use App\Models\PlanoManutencao;
use App\Models\Usuario;
use App\Models\Veiculo;
use App\Services\VeiculoService;
use Database\Seeders\PerfilSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ManutencaoTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $admin;

    private Usuario $gestor;

    private Usuario $financeiro;

    private Usuario $geral;

    private Veiculo $veiculo;

    private Fornecedor $fornecedor;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(PerfilSeeder::class);

        $this->admin = $this->usuario('admin', 'admin', '11144477735');
        $this->gestor = $this->usuario('gestor', 'gestor', '52998224725', ['gestor_id' => $this->admin->id, 'pode_dirigir' => true]);
        $this->financeiro = $this->usuario('financeiro', 'fin', '15350946056');
        $this->geral = $this->usuario('geral', 'geral', '39053344705', ['gestor_id' => $this->gestor->id]);

        $this->actingAs($this->admin);
        $this->veiculo = app(VeiculoService::class)->criar([
            'nome' => 'Onix teste', 'placa' => 'BRA2E19', 'marca' => 'Chevrolet', 'modelo' => 'Onix',
            'ano_fabricacao' => 2023, 'ano_modelo' => 2024, 'estado_inicial' => 'bom', 'km_inicial' => 10000,
        ]);
        auth()->logout();

        $this->fornecedor = Fornecedor::create(['razao_social' => 'Oficina X', 'ativo' => true]);
    }

    private function usuario(string $perfil, string $login, string $cpf, array $extra = []): Usuario
    {
        return Usuario::create(array_merge([
            'nome' => "Usuário {$login}", 'login' => $login, 'email' => "{$login}@teste.local", 'cpf' => $cpf,
            'senha' => 'segredo123', 'perfil_id' => Perfil::where('codigo', $perfil)->value('id'), 'ativo' => true,
        ], $extra))->fresh();
    }

    /** @return array<string, mixed> */
    private function dados(array $extra = []): array
    {
        return array_merge([
            'veiculo_id' => $this->veiculo->id, 'tipo' => 'imediata', 'nome' => 'Troca de pastilhas',
            'descricao_problema' => 'Freio chiando', 'fornecedor_id' => $this->fornecedor->id,
            'preco_previsto' => '1.250,00', 'prazo' => now()->addDays(3)->toDateString(), 'localizacao' => 'Oficina X',
            'sistemas' => ['freios'],
        ], $extra);
    }

    public function test_ciclo_completo_com_bloqueio_prestacao_conclusao_e_movimentacoes(): void
    {
        $this->actingAs($this->admin)->put(route('veiculos.condicoes', $this->veiculo), ['condicoes' => ['freios' => ['situacao' => 'critico']]]);

        // Abertura com bloqueio: veículo indisponível; financeiro avisado.
        $this->actingAs($this->gestor)->post(route('manutencoes.store'), $this->dados(['bloquear_veiculo' => 1]))->assertRedirect();
        $m = Manutencao::firstOrFail();
        $this->assertSame('1250.00', $m->preco_previsto);
        $this->assertSame(10000, $m->km_abertura);
        $this->assertTrue($m->bloqueou_veiculo);
        $this->assertSame(SituacaoVeiculo::Indisponivel, $this->veiculo->fresh()->situacao);
        $this->assertTrue(Notificacao::where('usuario_id', $this->financeiro->id)->where('tipo', 'manutencao_aberta')->exists());

        // Edição gera movimentação legível.
        $this->actingAs($this->gestor)->put(route('manutencoes.update', $m), $this->dados(['preco_previsto' => '1.400,00', 'nome' => 'Troca de pastilhas e discos']))
            ->assertRedirect(route('manutencoes.show', $m));
        $alteracao = $m->movimentacoes()->where('tipo', 'alteracao')->firstOrFail();
        $this->assertStringContainsString('preço previsto', $alteracao->descricao);
        $this->assertSame('R$ 1.400,00', $alteracao->valores_novos['preço previsto']);

        // Iniciar prestação: veículo em manutenção.
        $this->actingAs($this->gestor)->patch(route('manutencoes.iniciar', $m))->assertSessionHas('sucesso');
        $this->assertSame(SituacaoManutencao::EmPrestacao, $m->fresh()->situacao);
        $this->assertSame(SituacaoVeiculo::EmManutencao, $this->veiculo->fresh()->situacao);

        // Comentário e anexo (financeiro pode anotar, não pode concluir).
        $this->actingAs($this->financeiro)->post(route('manutencoes.comentar', $m), ['comentario' => 'Orçamento aprovado'])->assertSessionHas('sucesso');
        $this->actingAs($this->financeiro)->post(route('manutencoes.anexar', $m), ['arquivo' => UploadedFile::fake()->create('nf.pdf', 120, 'application/pdf'), 'titulo' => 'Nota fiscal'])->assertSessionHas('sucesso');
        $anexo = $m->anexos()->firstOrFail();
        Storage::disk('local')->assertExists($anexo->caminho);
        $this->actingAs($this->financeiro)->get(route('manutencoes.anexo', $anexo))->assertOk();
        $this->actingAs($this->financeiro)->patch(route('manutencoes.concluir', $m), ['preco_final' => '1.380,50'])->assertForbidden();

        // Conclusão: km menor é recusado; depois conclui, freios OK, veículo disponível.
        $this->actingAs($this->gestor)->patch(route('manutencoes.concluir', $m), ['preco_final' => '1.380,50', 'km_conclusao' => 9000])->assertSessionHas('erro');
        $this->actingAs($this->gestor)->patch(route('manutencoes.concluir', $m), ['preco_final' => '1.380,50', 'km_conclusao' => 10012, 'estado_atual' => 'otimo'])->assertSessionHas('sucesso');

        $m->refresh();
        $this->assertSame(SituacaoManutencao::Prestada, $m->situacao);
        $this->assertSame('1380.50', $m->preco_final);
        $veiculo = $this->veiculo->fresh(['condicoes']);
        $this->assertSame(SituacaoVeiculo::Disponivel, $veiculo->situacao);
        $this->assertSame(10012, $veiculo->km_atual);
        $this->assertSame('otimo', $veiculo->estado_atual->value);
        $this->assertSame(SituacaoCondicao::Ok, $veiculo->condicoes->firstWhere('sistema', 'freios')->situacao);
        $this->assertSame(['abertura', 'situacao', 'alteracao', 'situacao', 'comentario', 'anexo', 'situacao'],
            $m->movimentacoes()->reorder('id')->pluck('tipo')->all());

        // Encerrada não edita.
        $this->actingAs($this->gestor)->get(route('manutencoes.edit', $m))->assertRedirect(route('manutencoes.show', $m));
    }

    public function test_permissoes_por_perfil(): void
    {
        $this->actingAs($this->gestor)->post(route('manutencoes.store'), $this->dados());
        $m = Manutencao::firstOrFail();

        $this->actingAs($this->geral)->get(route('manutencoes.index'))->assertForbidden();
        $this->actingAs($this->geral)->get(route('manutencoes.show', $m))->assertForbidden();
        $this->actingAs($this->financeiro)->get(route('manutencoes.index'))->assertOk()->assertSee('Troca de pastilhas');
        $this->actingAs($this->financeiro)->get(route('manutencoes.create'))->assertForbidden();
        $this->actingAs($this->financeiro)->post(route('manutencoes.store'), $this->dados())->assertForbidden();
    }

    public function test_prestacao_bloqueada_com_veiculo_em_uso_e_cancelamento_libera(): void
    {
        $this->actingAs($this->admin);
        app(VeiculoService::class)->mudarSituacao($this->veiculo, SituacaoVeiculo::EmUso, 'alocacao');

        $this->actingAs($this->gestor)->post(route('manutencoes.store'), $this->dados(['bloquear_veiculo' => 1]));
        $m = Manutencao::firstOrFail();
        $this->assertTrue($m->bloqueou_veiculo, 'o pedido de bloqueio fica registrado para o retorno');
        $this->assertSame(SituacaoVeiculo::EmUso, $this->veiculo->fresh()->situacao, 'mas não tira o carro de quem está com ele');
        $this->actingAs($this->gestor)->patch(route('manutencoes.iniciar', $m))->assertSessionHas('erro');

        $this->actingAs($this->admin);
        app(VeiculoService::class)->mudarSituacao($this->veiculo->fresh(), SituacaoVeiculo::Disponivel, 'alocacao');

        $this->actingAs($this->gestor)->patch(route('manutencoes.iniciar', $m))->assertSessionHas('sucesso');
        $this->actingAs($this->gestor)->patch(route('manutencoes.cancelar', $m), ['motivo' => 'Oficina sem peça'])->assertSessionHas('sucesso');
        $this->assertSame(SituacaoManutencao::Cancelada, $m->fresh()->situacao);
        $this->assertSame(SituacaoVeiculo::Disponivel, $this->veiculo->fresh()->situacao);
    }

    public function test_plano_preventivo_abre_manutencao_e_conclusao_atualiza_plano(): void
    {
        $this->actingAs($this->gestor)->post(route('planos.store', $this->veiculo), [
            'nome' => 'Troca de óleo', 'intervalo_km' => 10000, 'ultimo_km' => 1000, 'ultima_data' => now()->subMonth()->toDateString(),
        ])->assertSessionHas('sucesso');
        $plano = PlanoManutencao::firstOrFail();

        // Km atual 10.000 ≥ 11.000 - 500? Não. Nada abre.
        $this->artisan('manutencoes:verificar-planos')->assertSuccessful();
        $this->assertSame(0, Manutencao::count());

        $this->actingAs($this->admin);
        app(VeiculoService::class)->atualizarKm($this->veiculo, 10600);
        auth()->logout();

        $this->artisan('manutencoes:verificar-planos')->assertSuccessful();
        $this->artisan('manutencoes:verificar-planos')->assertSuccessful(); // não duplica
        $this->assertSame(1, Manutencao::count());

        $m = Manutencao::firstOrFail();
        $this->assertSame('preventiva', $m->tipo->value);
        $this->assertNull($m->aberta_por_id);
        $this->assertSame($plano->id, $m->plano_manutencao_id);

        $this->actingAs($this->gestor)->patch(route('manutencoes.iniciar', $m));
        $this->actingAs($this->gestor)->patch(route('manutencoes.concluir', $m), ['preco_final' => '350', 'km_conclusao' => 10620]);

        $plano->refresh();
        $this->assertSame(10620, $plano->ultimo_km);
        $this->assertTrue($plano->ultima_data->isToday());
        $this->assertFalse($plano->vencido(10620));
    }

    public function test_vencimentos_notificam_admin_e_motorista(): void
    {
        $this->veiculo->update(['licenciamento_validade' => now()->addDays(5)->toDateString()]);
        $this->gestor->update(['cnh_numero' => '123', 'cnh_validade' => now()->subDay()->toDateString()]);

        $this->artisan('frota:verificar-vencimentos')->assertSuccessful();
        $this->artisan('frota:verificar-vencimentos')->assertSuccessful(); // anti-spam: atualiza

        $this->assertSame(1, Notificacao::where('usuario_id', $this->admin->id)->where('tipo', 'vencimento_veiculos')->count());
        $this->assertSame(1, Notificacao::where('usuario_id', $this->gestor->id)->where('tipo', "cnh_vencimento_{$this->gestor->id}")->count());
    }

    public function test_fornecedor_com_manutencao_nao_e_excluido(): void
    {
        $this->actingAs($this->gestor)->post(route('manutencoes.store'), $this->dados());

        $this->actingAs($this->admin)->delete(route('fornecedores.destroy', $this->fornecedor))->assertSessionHas('erro');
        $this->assertNotSoftDeleted($this->fornecedor);
    }
}
