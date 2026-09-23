<?php

declare(strict_types=1);

namespace App\Http\Requests\Manutencao;

use App\Enums\TipoManutencao;
use App\Models\Manutencao;
use App\Support\Numero;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SalvarManutencaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manutencoes.gerenciar');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'preco_previsto' => Numero::dePtBr($this->input('preco_previsto')),
            'sistemas' => array_values(array_filter((array) $this->input('sistemas', []))),
        ]);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        /** @var Manutencao|null $manutencao */
        $manutencao = $this->route('manutencao');

        $regras = [
            'tipo' => ['required', Rule::in(TipoManutencao::valores())],
            'nome' => ['required', 'string', 'max:150'],
            'descricao_problema' => ['nullable', 'string', 'max:5000'],
            'fornecedor_id' => ['nullable', Rule::exists('fornecedores', 'id')->whereNull('deleted_at')],
            'preco_previsto' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'prazo' => ['nullable', 'date'],
            'localizacao' => ['nullable', 'string', 'max:255'],
            'responsavel_id' => ['nullable', 'exists:usuarios,id'],
            'sistemas' => ['array'],
            'sistemas.*' => [Rule::in(array_keys(config('frota.sistemas_mecanicos')))],
            'observacoes' => ['nullable', 'string', 'max:5000'],
        ];

        if ($manutencao === null) {
            $regras['veiculo_id'] = ['required', 'exists:veiculos,id'];
            $regras['ocorrencia_id'] = ['nullable', 'exists:ocorrencias,id'];
            $regras['bloquear_veiculo'] = ['nullable', 'boolean'];
        }

        return $regras;
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'veiculo_id' => 'veículo', 'tipo' => 'tipo', 'nome' => 'nome', 'descricao_problema' => 'descrição do problema',
            'fornecedor_id' => 'fornecedor', 'preco_previsto' => 'preço previsto', 'prazo' => 'prazo',
            'localizacao' => 'localização', 'responsavel_id' => 'responsável', 'sistemas' => 'sistemas',
            'observacoes' => 'observações', 'ocorrencia_id' => 'ocorrência',
        ];
    }

    /** @return array<string, mixed> */
    public function dadosParaGravar(): array
    {
        $dados = $this->validated();
        if (array_key_exists('bloquear_veiculo', $dados) || $this->route('manutencao') === null) {
            $dados['bloquear_veiculo'] = $this->boolean('bloquear_veiculo');
        }

        return $dados;
    }
}
