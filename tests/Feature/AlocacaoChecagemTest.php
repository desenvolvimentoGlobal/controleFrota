<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SituacaoAlocacao;
use App\Enums\SituacaoOcorrencia;
use App\Enums\SituacaoVeiculo;
use App\Models\Alocacao;
use App\Models\Checagem;
use App\Models\ChecagemFoto;
use App\Models\Notificacao;
use App\Models\Ocorrencia;
use App\Models\Perfil;
use App\Models\Usuario;
use App\Models\Veiculo;
use App\Services\VeiculoService;
use Database\Seeders\PerfilSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AlocacaoChecagemTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $admin;

    private Usuario $gestor;

    private Usuario $motorista1;

    private Usuario $motorista2;

    private Veiculo $veiculo;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(PerfilSeeder::class);

        $this->admin = $this->usuario('admin', 'admin', '11144477735');
        $this->gestor = $this->usuario('gestor', 'gestor', '52998224725', ['gestor_id' => $this->admin->id, 'pode_dirigir' => true]);
        $this->motorista1 = $this->usuario('geral', 'm1', '15350946056', ['gestor_id' => $this->gestor->id, 'pode_dirigir' => true]);
        $this->motorista2 = $this->usuario('geral', 'm2', '39053344705', ['gestor_id' => $this->gestor->id, 'pode_dirigir' => true]);

        $this->actingAs($this->admin);
        $this->veiculo = app(VeiculoService::class)->criar([
            'nome' => 'Onix teste', 'placa' => 'BRA2E19', 'marca' => 'Chevrolet', 'modelo' => 'Onix',
            'ano_fabricacao' => 2023, 'ano_modelo' => 2024, 'estado_inicial' => 'bom', 'km_inicial' => 1000,
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

    /** @return array<string, mixed> */
    private function dadosAlocacao(Usuario $motorista, array $extra = []): array
    {
        return array_merge([
            'veiculo_id' => $this->veiculo->id, 'motorista_id' => $motorista->id, 'objetivo' => 'Visita ao cliente',
            'saida_prevista' => now()->addHour()->format('Y-m-d H:i'), 'retorno_previsto' => now()->addHours(4)->format('Y-m-d H:i'),
        ], $extra);
    }

    /** Responde todos os itens com foto; anomalia nos itens informados. */
    private function responderItens(Checagem $checagem, Usuario $quem, array $anomalias = []): void
    {
        foreach ($checagem->itens as $item) {
            $anomalia = array_key_exists($item->item, $anomalias);
            $this->actingAs($quem)->postJson(route('checagens.item', [$checagem, $item]), [
                'foto' => UploadedFile::fake()->image("{$item->item}.jpg", 800, 600),
                'situacao' => $anomalia ? 'anomalia' : 'conforme',
                'observacao' => $anomalias[$item->item] ?? null,
            ])->assertOk()->assertJson(['ok' => true]);
        }
    }

    private function concluir(Checagem $checagem, Usuario $quem, int $km, string $estado = 'bom'): void
    {
        $this->actingAs($quem)->post(route('checagens.concluir', $checagem), [
            'km_informado' => $km, 'nivel_combustivel' => 'meio', 'estado_geral' => $estado,
        ])->assertRedirect(route('alocacoes.show', $checagem->alocacao_id))->assertSessionHas('sucesso');
    }

    public function test_fluxo_completo_com_ocorrencia_contestacao_e_revisao(): void
    {
        // 1) Motorista geral solicita: fica aguardando e o gestor é avisado.
        $this->actingAs($this->motorista1)->post(route('alocacoes.store'), $this->dadosAlocacao($this->motorista1))->assertRedirect();
        $alocacao1 = Alocacao::firstOrFail();
        $this->assertSame(SituacaoAlocacao::Solicitada, $alocacao1->situacao);
        $this->assertTrue(Notificacao::where('usuario_id', $this->gestor->id)->where('tipo', 'alocacao_solicitada')->exists());

        // 2) Gestor aprova: veículo reservado (saída é hoje).
        $this->actingAs($this->gestor)->patch(route('alocacoes.aprovar', $alocacao1))->assertSessionHas('sucesso');
        $this->assertSame(SituacaoAlocacao::Aprovada, $alocacao1->fresh()->situacao);
        $this->assertSame(SituacaoVeiculo::Reservado, $this->veiculo->fresh()->situacao);

        // 3) Checagem de saída: outro usuário não pode; o motorista abre o rascunho.
        $this->actingAs($this->motorista2)->post(route('alocacoes.checagem', $alocacao1))->assertForbidden();
        $this->actingAs($this->motorista1)->post(route('alocacoes.checagem', $alocacao1))->assertRedirect();
        $saida = $alocacao1->checagemSaida()->firstOrFail();
        $this->assertNull($saida->checagem_anterior_id, 'primeira checagem do veículo não tem anterior');

        // Não conclui com itens pendentes.
        $this->actingAs($this->motorista1)->post(route('checagens.concluir', $saida), ['km_informado' => 1000, 'nivel_combustivel' => 'meio', 'estado_geral' => 'bom'])
            ->assertSessionHas('erro');

        $this->responderItens($saida, $this->motorista1);
        $this->concluir($saida, $this->motorista1, 1000);

        $alocacao1->refresh();
        $this->assertSame(SituacaoAlocacao::EmUso, $alocacao1->situacao);
        $this->assertSame(1000, $alocacao1->km_saida);
        $this->assertSame(SituacaoVeiculo::EmUso, $this->veiculo->fresh()->situacao);

        // 4) Retorno com km menor é recusado; com km maior conclui e libera o veículo.
        $this->actingAs($this->motorista1)->post(route('alocacoes.checagem', $alocacao1));
        $retorno = $alocacao1->checagemRetorno()->firstOrFail();
        $this->assertSame($saida->id, $retorno->checagem_anterior_id);
        $this->responderItens($retorno, $this->motorista1);
        $this->actingAs($this->motorista1)->post(route('checagens.concluir', $retorno), ['km_informado' => 900, 'nivel_combustivel' => 'meio', 'estado_geral' => 'bom'])->assertSessionHas('erro');
        $this->concluir($retorno, $this->motorista1, 1120, 'regular');

        $alocacao1->refresh();
        $this->assertSame(SituacaoAlocacao::Concluida, $alocacao1->situacao);
        $this->assertSame(120, $alocacao1->kmRodados());
        $veiculo = $this->veiculo->fresh();
        $this->assertSame(SituacaoVeiculo::Disponivel, $veiculo->situacao);
        $this->assertSame(1120, $veiculo->km_atual);
        $this->assertSame('regular', $veiculo->estado_atual->value);

        // 5) Gestor aloca para o motorista 2: nasce aprovada. Na saída ele aponta anomalia.
        $this->actingAs($this->gestor)->post(route('alocacoes.store'), $this->dadosAlocacao($this->motorista2, [
            'saida_prevista' => now()->addHours(5)->format('Y-m-d H:i'), 'retorno_previsto' => now()->addHours(8)->format('Y-m-d H:i'),
        ]))->assertRedirect();
        $alocacao2 = Alocacao::latest('id')->firstOrFail();
        $this->assertSame(SituacaoAlocacao::Aprovada, $alocacao2->situacao);

        $this->actingAs($this->motorista2)->post(route('alocacoes.checagem', $alocacao2));
        $saida2 = $alocacao2->checagemSaida()->firstOrFail();
        $this->assertSame($retorno->id, $saida2->checagem_anterior_id, 'compara com o retorno do motorista anterior');
        $this->responderItens($saida2, $this->motorista2, ['lataria_frente' => 'Risco no para-choque']);
        $this->concluir($saida2, $this->motorista2, 1120);

        $ocorrencia = Ocorrencia::firstOrFail();
        $this->assertSame($alocacao1->id, $ocorrencia->alocacao_responsavel_id, 'responsável presumido é a alocação anterior');
        $this->assertSame($this->motorista2->id, $ocorrencia->apontada_por_id);
        $this->assertNotNull($ocorrencia->checagem_item_anterior_id);
        $this->assertTrue(Notificacao::where('usuario_id', $this->motorista1->id)->where('tipo', 'ocorrencia_contra_voce')->exists());
        $this->assertTrue(Notificacao::where('usuario_id', $this->gestor->id)->where('tipo', 'ocorrencia_aberta')->exists());

        // 6) Motorista 1 contesta; motorista 2 não pode contestar nem revisar; gestor revisa.
        $this->actingAs($this->motorista2)->patch(route('ocorrencias.contestar', $ocorrencia), ['contestacao' => 'x'])->assertForbidden();
        $this->actingAs($this->motorista1)->patch(route('ocorrencias.contestar', $ocorrencia), ['contestacao' => 'O risco já existia, veja minha foto de saída.'])->assertSessionHas('sucesso');
        $this->actingAs($this->motorista1)->patch(route('ocorrencias.revisar', $ocorrencia), ['decisao' => 'descartada'])->assertForbidden();
        $this->actingAs($this->gestor)->patch(route('ocorrencias.revisar', $ocorrencia), ['decisao' => 'descartada', 'observacao_revisao' => 'Dano antigo.'])->assertSessionHas('sucesso');
        $this->assertSame(SituacaoOcorrencia::Descartada, $ocorrencia->fresh()->situacao);

        // 7) Auditoria/visibilidade: motorista 1 vê a própria alocação, mas não a do 2.
        $this->actingAs($this->motorista1)->get(route('alocacoes.show', $alocacao1))->assertOk();
        $this->actingAs($this->motorista1)->get(route('alocacoes.show', $alocacao2))->assertForbidden();
        $this->actingAs($this->gestor)->get(route('alocacoes.show', $alocacao2))->assertOk();
        $foto = ChecagemFoto::whereHas('item', fn ($q) => $q->where('checagem_id', $saida2->id))->firstOrFail();
        $this->actingAs($this->motorista1)->get(route('checagens.foto', $foto))->assertOk();
    }

    public function test_conflito_de_agenda_veiculo_critico_e_motorista_nao_apto(): void
    {
        $this->actingAs($this->gestor)->post(route('alocacoes.store'), $this->dadosAlocacao($this->gestor))->assertRedirect();

        // Mesmo intervalo, outro motorista: conflito.
        $this->actingAs($this->gestor)->from(route('alocacoes.create'))
            ->post(route('alocacoes.store'), $this->dadosAlocacao($this->motorista1))->assertSessionHas('erro');

        // Intervalo depois: ok.
        $this->actingAs($this->gestor)->post(route('alocacoes.store'), $this->dadosAlocacao($this->motorista1, [
            'saida_prevista' => now()->addHours(5)->format('Y-m-d H:i'), 'retorno_previsto' => now()->addHours(6)->format('Y-m-d H:i'),
        ]))->assertSessionHas('sucesso');

        // Motorista sem pode_dirigir.
        $semCnh = $this->usuario('geral', 'm3', '12345678909', ['gestor_id' => $this->gestor->id]);
        $this->actingAs($this->gestor)->from(route('alocacoes.create'))
            ->post(route('alocacoes.store'), $this->dadosAlocacao($semCnh, [
                'saida_prevista' => now()->addDays(2)->format('Y-m-d H:i'), 'retorno_previsto' => now()->addDays(2)->addHour()->format('Y-m-d H:i'),
            ]))->assertSessionHas('erro');

        // Sistema crítico bloqueia.
        $this->actingAs($this->admin)->put(route('veiculos.condicoes', $this->veiculo), ['condicoes' => ['freios' => ['situacao' => 'critico']]]);
        $this->actingAs($this->gestor)->from(route('alocacoes.create'))
            ->post(route('alocacoes.store'), $this->dadosAlocacao($this->motorista2, [
                'saida_prevista' => now()->addDays(3)->format('Y-m-d H:i'), 'retorno_previsto' => now()->addDays(3)->addHour()->format('Y-m-d H:i'),
            ]))->assertSessionHas('erro');

        // Geral só aloca para si.
        $this->actingAs($this->motorista1)->from(route('alocacoes.create'))
            ->post(route('alocacoes.store'), $this->dadosAlocacao($this->motorista2, [
                'saida_prevista' => now()->addDays(4)->format('Y-m-d H:i'), 'retorno_previsto' => now()->addDays(4)->addHour()->format('Y-m-d H:i'),
            ]))->assertSessionHas('erro');
    }

    public function test_cancelar_libera_veiculo_e_atraso_e_marcado(): void
    {
        $this->actingAs($this->gestor)->post(route('alocacoes.store'), $this->dadosAlocacao($this->gestor));
        $alocacao = Alocacao::firstOrFail();
        $this->assertSame(SituacaoVeiculo::Reservado, $this->veiculo->fresh()->situacao);

        $this->actingAs($this->gestor)->patch(route('alocacoes.cancelar', $alocacao), ['motivo' => 'Cliente desmarcou'])->assertSessionHas('sucesso');
        $this->assertSame(SituacaoAlocacao::Cancelada, $alocacao->fresh()->situacao);
        $this->assertSame(SituacaoVeiculo::Disponivel, $this->veiculo->fresh()->situacao);

        // Atraso: em uso com retorno previsto no passado.
        $this->actingAs($this->gestor)->post(route('alocacoes.store'), $this->dadosAlocacao($this->gestor, [
            'saida_prevista' => now()->subHours(3)->format('Y-m-d H:i'), 'retorno_previsto' => now()->subHour()->format('Y-m-d H:i'),
        ]));
        $atrasada = Alocacao::latest('id')->firstOrFail();
        $this->actingAs($this->gestor)->post(route('alocacoes.checagem', $atrasada));
        $saida = $atrasada->checagemSaida()->firstOrFail();
        $this->responderItens($saida, $this->gestor);
        $this->concluir($saida, $this->gestor, 1000);

        $this->artisan('alocacoes:marcar-atrasadas')->assertSuccessful();
        $this->assertNotNull($atrasada->fresh()->atrasada_em);
        $this->assertTrue(Notificacao::where('usuario_id', $this->admin->id)->where('tipo', 'alocacao_atrasada')->exists());
    }

    public function test_retencao_apaga_fotos_antigas_mas_preserva_ocorrencia_aberta(): void
    {
        $this->actingAs($this->gestor)->post(route('alocacoes.store'), $this->dadosAlocacao($this->gestor));
        $alocacao = Alocacao::firstOrFail();
        $this->actingAs($this->gestor)->post(route('alocacoes.checagem', $alocacao));
        $saida = $alocacao->checagemSaida()->firstOrFail();
        $this->responderItens($saida, $this->gestor, ['odometro' => 'Vidro trincado']);
        $this->concluir($saida, $this->gestor, 1000);

        Checagem::query()->update(['concluida_em' => now()->subMonths(7)]);

        $this->artisan('checagens:apagar-fotos-antigas')->assertSuccessful();

        $fotoOcorrencia = ChecagemFoto::whereHas('item', fn ($q) => $q->where('item', 'odometro'))->firstOrFail();
        $outra = ChecagemFoto::whereHas('item', fn ($q) => $q->where('item', 'lataria_frente'))->firstOrFail();

        $this->assertNull($fotoOcorrencia->apagada_em, 'foto da ocorrência aberta é preservada');
        $this->assertNotNull($outra->apagada_em);
        Storage::disk('local')->assertMissing($outra->caminho);
        Storage::disk('local')->assertExists($fotoOcorrencia->caminho);
        $this->actingAs($this->gestor)->get(route('checagens.foto', $outra))->assertNotFound();
    }
}
