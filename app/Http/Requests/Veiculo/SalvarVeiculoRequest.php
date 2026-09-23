<?php

declare(strict_types=1);

namespace App\Http\Requests\Veiculo;

use App\Enums\CaracteristicasVeiculo as C;
use App\Enums\CondicaoVeiculo;
use App\Models\Veiculo;
use App\Rules\Placa;
use App\Support\Numero;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SalvarVeiculoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('frota.gerenciar');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'placa' => Placa::normalizar($this->input('placa')),
            'chassi' => strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $this->input('chassi')) ?? '') ?: null,
            'renavam' => preg_replace('/\D/', '', (string) $this->input('renavam')) ?: null,
            'valor_aquisicao' => Numero::dePtBr($this->input('valor_aquisicao')),
        ]);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        /** @var Veiculo|null $veiculo */
        $veiculo = $this->route('veiculo');
        $anoMax = (int) date('Y') + 1;

        $regras = [
            'nome' => ['required', 'string', 'max:80'],
            // Únicos só entre os não excluídos: veículo excluído não prende a placa.
            'placa' => ['required', new Placa, Rule::unique('veiculos', 'placa')->ignore($veiculo)->withoutTrashed()],
            'chassi' => ['nullable', 'string', 'size:17', Rule::unique('veiculos', 'chassi')->ignore($veiculo)->withoutTrashed()],
            'renavam' => ['nullable', 'digits_between:9,11'],

            'marca' => ['required', 'string', 'max:60'],
            'modelo' => ['required', 'string', 'max:60'],
            'versao' => ['nullable', 'string', 'max:60'],
            'ano_fabricacao' => ['required', 'integer', 'min:1950', "max:{$anoMax}"],
            'ano_modelo' => ['required', 'integer', 'min:1950', "max:{$anoMax}", 'gte:ano_fabricacao'],
            'carroceria' => ['nullable', Rule::in(C::chaves(C::CARROCERIAS))],
            'cor' => ['nullable', 'string', 'max:40'],
            'tipo_cor' => ['nullable', Rule::in(C::chaves(C::TIPOS_COR))],
            'portas' => ['nullable', 'integer', 'between:2,6'],
            'lugares' => ['nullable', 'integer', 'between:1,20'],

            'motor' => ['nullable', 'string', 'max:20'],
            'potencia_cv' => ['nullable', 'integer', 'between:1,2000'],
            'combustivel' => ['nullable', Rule::in(C::chaves(C::COMBUSTIVEIS))],
            'cambio' => ['nullable', Rule::in(C::chaves(C::CAMBIOS))],
            'tracao' => ['nullable', Rule::in(C::chaves(C::TRACOES))],
            'direcao' => ['nullable', Rule::in(C::chaves(C::DIRECOES))],

            'ar_condicionado' => ['nullable', Rule::in(C::chaves(C::AR_CONDICIONADO))],
            'bancos' => ['nullable', Rule::in(C::chaves(C::BANCOS))],
            'airbags' => ['nullable', 'string', 'max:60'],
            'nota_latin_ncap' => ['nullable', 'integer', 'between:0,5'],

            'data_aquisicao' => ['nullable', 'date'],
            'valor_aquisicao' => ['nullable', 'numeric', 'min:0'],
            'licenciamento_validade' => ['nullable', 'date'],
            'seguro_validade' => ['nullable', 'date'],
            'seguradora' => ['nullable', 'string', 'max:100'],
            'observacoes' => ['nullable', 'string', 'max:2000'],
            'foto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ];

        foreach (array_merge(C::chaves(C::CONFORTO), C::chaves(C::SEGURANCA)) as $bool) {
            $regras[$bool] = ['nullable', 'boolean'];
        }

        // Só no cadastro: estado e km iniciais. Depois disso mudam pelo fluxo.
        if ($veiculo === null) {
            $regras['estado_inicial'] = ['required', Rule::in(CondicaoVeiculo::valores())];
            $regras['km_inicial'] = ['required', 'integer', 'min:0', 'max:9999999'];
        }

        return $regras;
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'nome' => 'nome do veículo', 'placa' => 'placa', 'chassi' => 'chassi (VIN)', 'renavam' => 'RENAVAM',
            'marca' => 'marca', 'modelo' => 'modelo', 'versao' => 'versão', 'ano_fabricacao' => 'ano de fabricação',
            'ano_modelo' => 'ano-modelo', 'carroceria' => 'carroceria', 'cor' => 'cor', 'tipo_cor' => 'tipo de cor',
            'portas' => 'portas', 'lugares' => 'lugares', 'motor' => 'motor', 'potencia_cv' => 'potência',
            'combustivel' => 'combustível', 'cambio' => 'câmbio', 'tracao' => 'tração', 'direcao' => 'direção',
            'ar_condicionado' => 'ar-condicionado', 'bancos' => 'bancos', 'airbags' => 'airbags',
            'nota_latin_ncap' => 'nota Latin NCAP', 'estado_inicial' => 'estado inicial', 'km_inicial' => 'km inicial',
            'data_aquisicao' => 'data de aquisição', 'valor_aquisicao' => 'valor de aquisição',
            'licenciamento_validade' => 'validade do licenciamento', 'seguro_validade' => 'validade do seguro',
            'seguradora' => 'seguradora', 'observacoes' => 'observações', 'foto' => 'foto',
        ];
    }

    /** Dados prontos para o model: booleanos resolvidos, sem a foto. */
    public function dadosParaGravar(): array
    {
        $dados = $this->validated();
        unset($dados['foto']);

        foreach (array_merge(C::chaves(C::CONFORTO), C::chaves(C::SEGURANCA)) as $bool) {
            $dados[$bool] = $this->boolean($bool);
        }

        return $dados;
    }
}
