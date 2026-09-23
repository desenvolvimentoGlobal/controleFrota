<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Fornecedor;
use App\Models\Usuario;
use App\Models\Veiculo;
use App\Services\VeiculoService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/** Veículos e fornecedores de DESENVOLVIMENTO. Não roda em produção. */
class VeiculoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction() || Veiculo::exists()) {
            return;
        }

        // O histórico registra quem cadastrou; no seeder é o admin.
        Auth::login(Usuario::where('login', 'admin')->firstOrFail());
        $servico = app(VeiculoService::class);

        $servico->criar([
            'nome' => 'Onix branco 01', 'placa' => 'BRA2E19', 'chassi' => '9BGKS48U0PG123456',
            'marca' => 'Chevrolet', 'modelo' => 'Onix', 'versao' => 'LT 1.0 Turbo', 'ano_fabricacao' => 2023, 'ano_modelo' => 2024,
            'carroceria' => 'hatch', 'cor' => 'Branco', 'tipo_cor' => 'solida', 'portas' => 4, 'lugares' => 5,
            'motor' => '1.0 Turbo', 'potencia_cv' => 116, 'combustivel' => 'flex', 'cambio' => 'automatico', 'tracao' => 'dianteira', 'direcao' => 'eletrica',
            'ar_condicionado' => 'digital', 'bancos' => 'tecido', 'central_multimidia' => true, 'pareamento_smartphone' => true,
            'painel_digital' => false, 'vidros_eletricos' => true, 'travas_eletricas' => true,
            'abs' => true, 'esc' => true, 'sensor_ponto_cego' => false, 'cinto_tres_pontos' => true, 'isofix' => true,
            'airbags' => '6 airbags', 'nota_latin_ncap' => 5,
            'estado_inicial' => 'otimo', 'km_inicial' => 12500,
            'data_aquisicao' => '2024-02-10', 'valor_aquisicao' => 98500, 'licenciamento_validade' => now()->addDays(20)->toDateString(),
            'seguro_validade' => now()->addMonths(8)->toDateString(), 'seguradora' => 'Porto Seguro',
        ]);

        $onix = Veiculo::where('placa', 'BRA2E19')->first();
        $onix?->planosManutencao()->create([
            'nome' => 'Troca de óleo e filtros', 'intervalo_km' => 10000, 'intervalo_dias' => 180,
            'ultimo_km' => 12500, 'ultima_data' => now()->subMonths(2)->toDateString(), 'ativo' => true,
        ]);

        $servico->criar([
            'nome' => 'Strada prata', 'placa' => 'ABC1234', 'chassi' => '9BD281A2NPY654321',
            'marca' => 'Fiat', 'modelo' => 'Strada', 'versao' => 'Freedom 1.3', 'ano_fabricacao' => 2022, 'ano_modelo' => 2022,
            'carroceria' => 'picape', 'cor' => 'Prata', 'tipo_cor' => 'metalica', 'portas' => 4, 'lugares' => 5,
            'motor' => '1.3', 'potencia_cv' => 107, 'combustivel' => 'flex', 'cambio' => 'manual', 'tracao' => 'dianteira', 'direcao' => 'hidraulica',
            'ar_condicionado' => 'manual', 'bancos' => 'tecido', 'vidros_eletricos' => true, 'travas_eletricas' => true,
            'abs' => true, 'esc' => true, 'cinto_tres_pontos' => true, 'airbags' => 'frontais', 'nota_latin_ncap' => 4,
            'estado_inicial' => 'bom', 'km_inicial' => 48200,
            'data_aquisicao' => '2022-06-01', 'valor_aquisicao' => 82000, 'licenciamento_validade' => now()->addMonths(5)->toDateString(),
        ]);

        Auth::logout();

        Fornecedor::firstOrCreate(['cnpj' => '11222333000181'], [
            'razao_social' => 'Oficina Mecânica Central Ltda', 'nome_fantasia' => 'Oficina Central',
            'telefone' => '11987654321', 'email' => 'contato@oficinacentral.com.br', 'contato' => 'Carlos',
            'cidade' => 'São Paulo', 'uf' => 'SP', 'ativo' => true,
        ]);
    }
}
