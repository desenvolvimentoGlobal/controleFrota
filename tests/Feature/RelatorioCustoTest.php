<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Alocacao;
use App\Models\Fornecedor;
use App\Models\LogAuditoria;
use App\Models\Manutencao;
use App\Models\Perfil;
use App\Models\Usuario;
use App\Models\Veiculo;
use App\Services\RelatorioCustoService;
use App\Services\VeiculoService;
use Database\Seeders\PerfilSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RelatorioCustoTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $admin;

    private Usuario $financeiro;

    private Usuario $gestor;

    private Veiculo $onix;

    private Veiculo $strada;

    private Fornecedor $oficina;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PerfilSeeder::class);

        $this->admin = $this->usuario('admin', 'admin', '11144477735');
        $this->financeiro = $this->usuario('financeiro', 'fin', '15350946056');
        $this->gestor = $this->usuario('gestor', 'gestor', '52998224725');

        $this->actingAs($this->admin);
        $servico = app(VeiculoService::class);
        $base = ['marca' => 'X', 'modelo' => 'Y', 'ano_fabricacao' => 2022, 'ano_modelo' => 2022, 'estado_inicial' => 'bom', 'km_inicial' => 1000];
        $this->onix = $servico->criar($base + ['nome' => 'Onix', 'placa' => 'BRA2E19']);
        $this->strada = $servico->criar($base + ['nome' => 'Strada', 'placa' => 'ABC1234']);
        auth()->logout();

        $this->oficina = Fornecedor::create(['razao_social' => 'Oficina Central', 'ativo' => true]);

        // No período (setembro/2026):
        $this->manutencao($this->onix, 'preventiva', 1000, 900, '2026-09-10', $this->oficina);
        $this->manutencao($this->onix, 'imediata', 500, 600, '2026-09-15', $this->oficina);
        $this->manutencao($this->strada, 'imediata', 1500, 1500, '2026-08-20', null);
        // Fora do período:
        $this->manutencao($this->strada, 'preventiva', 9999, 9999, '2025-12-01', $this->oficina);
        // Aberta: entra só como comprometido.
        Manutencao::create(['veiculo_id' => $this->onix->id, 'tipo' => 'planejada', 'nome' => 'Pneus', 'situacao' => 'em_espera', 'preco_previsto' => 800, 'aberta_por_id' => $this->admin->id]);

        // Km rodados do Onix no período: 1.000 + 250.
        $this->alocacao($this->onix, 1000, 2000, '2026-09-05');
        $this->alocacao($this->onix, 2000, 2250, '2026-09-20');
    }

    private function usuario(string $perfil, string $login, string $cpf): Usuario
    {
        return Usuario::create([
            'nome' => "Usuário {$login}", 'login' => $login, 'email' => "{$login}@teste.local", 'cpf' => $cpf,
            'senha' => 'segredo123', 'perfil_id' => Perfil::where('codigo', $perfil)->value('id'), 'ativo' => true, 'pode_dirigir' => true,
        ])->fresh();
    }

    private function manutencao(Veiculo $v, string $tipo, float $previsto, float $final, string $concluida, ?Fornecedor $f): void
    {
        Manutencao::create([
            'veiculo_id' => $v->id, 'tipo' => $tipo, 'nome' => "Serviço {$tipo}", 'situacao' => 'prestada',
            'preco_previsto' => $previsto, 'preco_final' => $final, 'concluida_em' => "{$concluida} 10:00:00",
            'fornecedor_id' => $f?->id, 'aberta_por_id' => $this->admin->id,
        ]);
    }

    private function alocacao(Veiculo $v, int $kmSaida, int $kmRetorno, string $retorno): void
    {
        Alocacao::create([
            'veiculo_id' => $v->id, 'motorista_id' => $this->gestor->id, 'solicitante_id' => $this->gestor->id,
            'objetivo' => 'x', 'saida_prevista' => "{$retorno} 08:00", 'retorno_previsto' => "{$retorno} 18:00",
            'saida_real' => "{$retorno} 08:00", 'retorno_real' => "{$retorno} 18:00",
            'km_saida' => $kmSaida, 'km_retorno' => $kmRetorno, 'situacao' => 'concluida',
        ]);
    }

    public function test_agrega_custos_por_veiculo_com_custo_por_km_e_comprometido(): void
    {
        $dados = app(RelatorioCustoService::class)->gerar(['de' => '2026-08-01', 'ate' => '2026-09-30', 'visao' => 'veiculo']);

        $r = $dados['resumo'];
        $this->assertSame(3, $r['quantidade']);
        $this->assertEqualsWithDelta(3000.0, $r['total'], 0.001);
        $this->assertEqualsWithDelta(3000.0, $r['previsto'], 0.001);
        $this->assertEqualsWithDelta(0.0, $r['variacao'], 0.001);
        $this->assertSame(1250, $r['km']);
        $this->assertEqualsWithDelta(800.0, $r['comprometido'], 0.001);
        $this->assertSame(1, $r['comprometido_qtd']);

        // Onix = 900 + 600; Strada = 1.500 (a de 2025 fica fora do período).
        $porNome = collect($dados['linhas'])->keyBy('rotulo');
        $this->assertEqualsWithDelta(1500.0, $porNome['Onix']['custo'], 0.001);
        $this->assertSame(1250, $porNome['Onix']['km']);
        $this->assertEqualsWithDelta(1.2, $porNome['Onix']['custo_km'], 0.001);
        $this->assertNull($porNome['Strada']['custo_km'], 'sem km rodado não há custo por km');
        $this->assertEqualsWithDelta(50.0, $porNome['Strada']['percentual'], 0.001);

        $this->assertSame(['ago/26', 'set/26'], array_map('mb_strtolower', $dados['mensal']['rotulos']));
        $this->assertSame([1500.0, 1500.0], $dados['mensal']['valores']);
    }

    public function test_outras_visoes_e_filtros(): void
    {
        $servico = app(RelatorioCustoService::class);

        $porFornecedor = collect($servico->gerar(['de' => '2026-08-01', 'ate' => '2026-09-30', 'visao' => 'fornecedor'])['linhas'])->keyBy('rotulo');
        $this->assertEqualsWithDelta(1500.0, $porFornecedor['Oficina Central']['custo'], 0.001);
        $this->assertEqualsWithDelta(1500.0, $porFornecedor['Sem fornecedor']['custo'], 0.001);

        $porTipo = collect($servico->gerar(['de' => '2026-08-01', 'ate' => '2026-09-30', 'visao' => 'tipo'])['linhas'])->keyBy('chave');
        $this->assertSame(2, $porTipo['imediata']['quantidade']);

        $soOnix = $servico->gerar(['de' => '2026-08-01', 'ate' => '2026-09-30', 'veiculo_id' => $this->onix->id, 'visao' => 'mes']);
        $this->assertEqualsWithDelta(1500.0, $soOnix['resumo']['total'], 0.001);
        $this->assertEqualsWithDelta(1.2, $soOnix['resumo']['custo_km'], 0.001);
        $this->assertStringContainsString('Onix', $soOnix['filtrosLabel']);

        // Variação: onix previsto 1.500, final 1.500 → 0; só a imediata: previsto 500, final 600 → +20%.
        $soImediataOnix = $servico->gerar(['de' => '2026-09-01', 'ate' => '2026-09-30', 'veiculo_id' => $this->onix->id, 'tipo' => 'imediata']);
        $this->assertEqualsWithDelta(20.0, $soImediataOnix['resumo']['variacao'], 0.001);

        // Datas invertidas são corrigidas; visão inválida cai no padrão.
        $invertido = $servico->gerar(['de' => '2026-09-30', 'ate' => '2026-08-01', 'visao' => 'hackeado']);
        $this->assertSame(3, $invertido['resumo']['quantidade']);
        $this->assertSame('veiculo', $invertido['visao']);
    }

    public function test_tela_e_exportacoes_com_permissao_e_auditoria(): void
    {
        $filtros = ['de' => '2026-08-01', 'ate' => '2026-09-30', 'visao' => 'veiculo'];

        $this->actingAs($this->gestor)->get(route('relatorios.custos', $filtros))->assertForbidden();
        $this->actingAs($this->gestor)->get(route('relatorios.custos.pdf', $filtros))->assertForbidden();

        $this->actingAs($this->financeiro)->get(route('relatorios.custos', $filtros))
            ->assertOk()->assertSee('Onix')->assertSee('R$ 3.000,00');

        $pdf = $this->actingAs($this->financeiro)->get(route('relatorios.custos.pdf', $filtros));
        $pdf->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $pdf->headers->get('Content-Type'));
        $this->assertStringContainsString('custos-manutencao-veiculo-20260801-20260930.pdf', (string) $pdf->headers->get('Content-Disposition'));

        $excel = $this->actingAs($this->admin)->get(route('relatorios.custos.excel', $filtros));
        $excel->assertOk();
        $this->assertStringContainsString('.xlsx', (string) $excel->headers->get('Content-Disposition'));

        $this->assertSame(2, LogAuditoria::where('acao', 'exportou')->count());

        $this->actingAs($this->financeiro)->get(route('painel'))->assertOk()->assertSee('Veículos que mais custaram');
        $this->actingAs($this->gestor)->get(route('painel'))->assertOk()->assertDontSee('Veículos que mais custaram');
    }
}
