<?php

declare(strict_types=1);

namespace App\Http\Requests\Fornecedor;

use App\Http\Requests\Usuario\SalvarUsuarioRequest;
use App\Models\Fornecedor;
use App\Rules\Cnpj;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SalvarFornecedorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('fornecedores.gerenciar');
    }

    protected function prepareForValidation(): void
    {
        $digitos = fn (mixed $v) => preg_replace('/\D/', '', (string) $v) ?: null;

        $this->merge([
            'cnpj' => $digitos($this->input('cnpj')),
            'telefone' => $digitos($this->input('telefone')),
            'cep' => $digitos($this->input('cep')),
            'uf' => strtoupper((string) $this->input('uf')) ?: null,
        ]);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        /** @var Fornecedor|null $fornecedor */
        $fornecedor = $this->route('fornecedor');

        return [
            'razao_social' => ['required', 'string', 'max:255'],
            'nome_fantasia' => ['nullable', 'string', 'max:255'],
            'cnpj' => ['nullable', 'digits:14', new Cnpj, Rule::unique('fornecedores', 'cnpj')->ignore($fornecedor)],
            'telefone' => ['nullable', 'digits_between:10,11'],
            'email' => ['nullable', 'email', 'max:255'],
            'contato' => ['nullable', 'string', 'max:100'],
            'cep' => ['nullable', 'digits:8'],
            'logradouro' => ['nullable', 'string', 'max:255'],
            'numero' => ['nullable', 'string', 'max:20'],
            'complemento' => ['nullable', 'string', 'max:100'],
            'bairro' => ['nullable', 'string', 'max:100'],
            'cidade' => ['nullable', 'string', 'max:100'],
            'uf' => ['nullable', 'size:2', Rule::in(SalvarUsuarioRequest::UFS)],
            'observacoes' => ['nullable', 'string', 'max:2000'],
            'ativo' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'razao_social' => 'razão social', 'nome_fantasia' => 'nome fantasia', 'cnpj' => 'CNPJ',
            'telefone' => 'telefone', 'email' => 'e-mail', 'contato' => 'contato', 'cep' => 'CEP',
            'logradouro' => 'logradouro', 'numero' => 'número', 'complemento' => 'complemento',
            'bairro' => 'bairro', 'cidade' => 'cidade', 'uf' => 'UF', 'observacoes' => 'observações',
        ];
    }
}
