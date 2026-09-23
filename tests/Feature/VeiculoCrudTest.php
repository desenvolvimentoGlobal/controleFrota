<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CondicaoVeiculo;
use App\Enums\SituacaoVeiculo;
use App\Models\Perfil;
use App\Models\Usuario;
use App\Models\Veiculo;
use App\Services\VeiculoService;
use Database\Seeders\PerfilSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VeiculoCrudTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(string $perfil, string $cpf = '11144477735'): Usuario
    {
        if (Perfil::count() === 0) {
            $this->seed(PerfilSeeder::class);
        }

        return Usuario::create([
            'nome' => "Usuário {$perfil}", 'login' => "u.{$perfil}", 'email' => "{$perfil}@teste.local",
            'cpf' => $cpf, 'senha' => 'segredo123',
            'perfil_id' => Perfil::where('codigo', $perfil)->value('id'), 'ativo' => true,
        ])->fresh();
    }

    /** @return array<string, mixed> */
    private function dados(array $extra = []): array
    {
        return array_merge([
            'nome' => 'Onix teste', 'placa' => 'bra-2e19', 'chassi' => '9bgks48u0pg123456',
            'marca' => 'Chevrolet', 'modelo' => 'Onix', 'ano_fabricacao' => 2023, 'ano_modelo' => 2024,
            'carroceria' => 'hatch', 'combustivel' => 'flex', 'abs' => 1,
            'estado_inicial' => 'bom', 'km_inicial' => 1000, 'valor_aquisicao' => '98.500,50',
        ], $extra);
    }

    public function test_gestor_cadastra_veiculo_com_condicoes_e_historico(): void
    {
        $gestor = $this->usuario('gestor');

        $this->actingAs($gestor)->post(route('veiculos.store'), $this->dados())->assertRedirect();

        $veiculo = Veiculo::where('placa', 'BRA2E19')->firstOrFail();
        $this->assertSame('9BGKS48U0PG123456', $veiculo->chassi);
        $this->assertSame('98500.50', $veiculo->valor_aquisicao);
        $this->assertTrue($veiculo->abs);
        $this->assertFalse($veiculo->esc);
        $this->assertSame(SituacaoVeiculo::Disponivel, $veiculo->situacao);
        $this->assertSame(CondicaoVeiculo::Bom, $veiculo->estado_atual);
        $this->assertSame(1000, $veiculo->km_atual);
        $this->assertCount(count(config('frota.sistemas_mecanicos')), $veiculo->condicoes);
        $this->assertCount(3, $veiculo->historicoEstados);
    }

    public function test_placa_invalida_ano_modelo_menor_e_placa_duplicada(): void
    {
        $admin = $this->usuario('admin');

        $this->actingAs($admin)->from(route('veiculos.create'))
            ->post(route('veiculos.store'), $this->dados(['placa' => 'AB12345']))->assertSessionHasErrors('placa');

        $this->actingAs($admin)->from(route('veiculos.create'))
            ->post(route('veiculos.store'), $this->dados(['ano_modelo' => 2022]))->assertSessionHasErrors('ano_modelo');

        $this->actingAs($admin)->post(route('veiculos.store'), $this->dados());
        $this->actingAs($admin)->from(route('veiculos.create'))
            ->post(route('veiculos.store'), $this->dados(['placa' => 'BRA2E19', 'chassi' => null]))->assertSessionHasErrors('placa');
    }

    public function test_perfil_geral_consulta_mas_nao_edita(): void
    {
        $admin = $this->usuario('admin');
        $this->actingAs($admin)->post(route('veiculos.store'), $this->dados());
        $veiculo = Veiculo::firstOrFail();

        $geral = $this->usuario('geral', '52998224725');
        $this->actingAs($geral)->get(route('veiculos.index'))->assertOk()->assertSee('Onix teste');
        $this->actingAs($geral)->get(route('veiculos.show', $veiculo))->assertOk();
        $this->actingAs($geral)->get(route('veiculos.create'))->assertForbidden();
        $this->actingAs($geral)->put(route('veiculos.update', $veiculo), $this->dados())->assertForbidden();
        $this->actingAs($geral)->put(route('veiculos.condicoes', $veiculo), ['condicoes' => []])->assertForbidden();
    }

    public function test_condicao_critica_bloqueia_alocacao_e_correcao_manual_de_km(): void
    {
        $admin = $this->usuario('admin');
        $this->actingAs($admin)->post(route('veiculos.store'), $this->dados());
        $veiculo = Veiculo::firstOrFail();

        $this->actingAs($admin)->put(route('veiculos.condicoes', $veiculo), [
            'condicoes' => ['freios' => ['situacao' => 'critico', 'observacao' => 'Pastilha no fim']],
        ])->assertSessionHas('aviso');

        $veiculo->refresh()->load('condicoes');
        $this->assertTrue($veiculo->temCondicaoCritica());
        $this->assertNotEmpty($veiculo->impedimentosParaAlocar());

        // Correção manual pode baixar o km (dígito a mais numa checagem),
        // com o motivo no histórico.
        $this->actingAs($admin)->from(route('veiculos.show', $veiculo))
            ->patch(route('veiculos.estado', $veiculo), ['estado_atual' => 'regular', 'km_atual' => 500, 'observacao' => 'Km digitado errado'])
            ->assertSessionHas('sucesso');

        $this->assertSame(500, $veiculo->fresh()->km_atual);
        $this->assertDatabaseHas('veiculo_historico_estados', [
            'veiculo_id' => $veiculo->id, 'campo' => 'km_atual', 'valor_anterior' => '1000', 'valor_novo' => '500', 'origem' => 'manual',
        ]);
    }

    public function test_mudanca_manual_de_situacao_gera_historico_e_baixado_so_volta_disponivel(): void
    {
        $admin = $this->usuario('admin');
        $this->actingAs($admin)->post(route('veiculos.store'), $this->dados());
        $veiculo = Veiculo::firstOrFail();

        $this->actingAs($admin)->patch(route('veiculos.situacao', $veiculo), ['situacao' => 'baixado', 'observacao' => 'Vendido'])
            ->assertSessionHas('sucesso');
        $this->assertSame(SituacaoVeiculo::Baixado, $veiculo->fresh()->situacao);
        $this->assertSame('Vendido', $veiculo->historicoEstados()->where('campo', 'situacao')->first()->observacao);

        $this->expectException(\DomainException::class);
        app(VeiculoService::class)->mudarSituacao($veiculo->fresh(), SituacaoVeiculo::Indisponivel);
    }

    public function test_cadastros_simples_e_fornecedores(): void
    {
        $admin = $this->usuario('admin');

        $this->actingAs($admin)->post(route('setores.store'), ['nome' => 'Logística'])->assertRedirect(route('setores.index'));
        $this->actingAs($admin)->from(route('setores.index'))->post(route('setores.store'), ['nome' => 'Logística'])->assertSessionHasErrors('nome');

        $this->actingAs($admin)->post(route('fornecedores.store'), [
            'razao_social' => 'Oficina X', 'cnpj' => '11.222.333/0001-81', 'telefone' => '(11) 98765-4321',
        ])->assertRedirect(route('fornecedores.index'));
        $this->assertDatabaseHas('fornecedores', ['cnpj' => '11222333000181', 'telefone' => '11987654321']);

        $this->actingAs($admin)->from(route('fornecedores.create'))
            ->post(route('fornecedores.store'), ['razao_social' => 'Y', 'cnpj' => '11.222.333/0001-82'])->assertSessionHasErrors('cnpj');

        $geral = $this->usuario('geral', '52998224725');
        $this->actingAs($geral)->get(route('fornecedores.index'))->assertForbidden();
        $this->actingAs($geral)->get(route('setores.index'))->assertForbidden();
    }
}
