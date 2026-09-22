<?php

declare(strict_types=1);

namespace App\Http\Requests\Usuario;

use App\Models\Usuario;
use App\Rules\Cpf;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Validação do cadastro de usuário (create e update). */
class SalvarUsuarioRequest extends FormRequest
{
    public const UFS = [
        'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS',
        'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC',
        'SP', 'SE', 'TO',
    ];

    public const CATEGORIAS_CNH = ['A', 'B', 'AB', 'C', 'AC', 'D', 'AD', 'E', 'AE'];

    public function authorize(): bool
    {
        return true; // a rota já passa por perfil:admin,gestor; o recorte está no controller
    }

    /** Guarda só dígitos em CPF/telefones/CEP, login minúsculo e UF maiúscula. */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'cpf' => $this->somenteDigitos($this->input('cpf')),
            'telefone' => $this->somenteDigitos($this->input('telefone')),
            'celular' => $this->somenteDigitos($this->input('celular')),
            'cep' => $this->somenteDigitos($this->input('cep')),
            'login' => mb_strtolower(trim((string) $this->input('login'))) ?: null,
            'uf' => strtoupper((string) $this->input('uf')) ?: null,
            'cnh_categoria' => strtoupper((string) $this->input('cnh_categoria')) ?: null,
        ]);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        /** @var Usuario|null $usuario */
        $usuario = $this->route('usuario');

        return [
            'nome' => ['required', 'string', 'max:255'],
            'login' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9._-]+$/', Rule::unique('usuarios', 'login')->ignore($usuario)],
            'email' => ['required', 'email', 'max:255', Rule::unique('usuarios', 'email')->ignore($usuario)],
            'cpf' => ['required', 'digits:11', new Cpf, Rule::unique('usuarios', 'cpf')->ignore($usuario)],
            'perfil_id' => ['required', 'exists:perfis,id'],
            'senha' => [$usuario ? 'nullable' : 'required', 'string', Password::min(8)->letters()->numbers()],
            'setor_id' => ['nullable', 'exists:setores,id'],
            'cargo_id' => ['nullable', 'exists:cargos,id'],
            'gestor_id' => ['nullable', 'exists:usuarios,id', $usuario ? Rule::notIn([$usuario->id]) : 'nullable'],

            'telefone' => ['nullable', 'digits_between:10,11'],
            'celular' => ['nullable', 'digits_between:10,11'],

            'cep' => ['nullable', 'digits:8'],
            'logradouro' => ['nullable', 'string', 'max:255'],
            'numero' => ['nullable', 'string', 'max:20'],
            'complemento' => ['nullable', 'string', 'max:100'],
            'bairro' => ['nullable', 'string', 'max:100'],
            'cidade' => ['nullable', 'string', 'max:100'],
            'uf' => ['nullable', 'string', 'size:2', Rule::in(self::UFS)],

            'pode_dirigir' => ['nullable', 'boolean'],
            'cnh_numero' => ['nullable', 'string', 'max:20'],
            'cnh_categoria' => ['nullable', Rule::in(self::CATEGORIAS_CNH)],
            'cnh_validade' => ['nullable', 'date'],

            'ativo' => ['nullable', 'boolean'],
            'deve_trocar_senha' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'nome' => 'nome', 'login' => 'login', 'email' => 'e-mail', 'cpf' => 'CPF',
            'perfil_id' => 'perfil', 'senha' => 'senha', 'setor_id' => 'setor', 'cargo_id' => 'cargo',
            'gestor_id' => 'gestor', 'telefone' => 'telefone', 'celular' => 'celular',
            'cep' => 'CEP', 'logradouro' => 'logradouro', 'numero' => 'número',
            'complemento' => 'complemento', 'bairro' => 'bairro', 'cidade' => 'cidade', 'uf' => 'UF',
            'pode_dirigir' => 'pode dirigir', 'cnh_numero' => 'número da CNH',
            'cnh_categoria' => 'categoria da CNH', 'cnh_validade' => 'validade da CNH',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'login.regex' => 'O login só pode ter letras minúsculas, números, ponto, hífen e sublinhado.',
            'gestor_id.not_in' => 'O usuário não pode ser gestor de si mesmo.',
        ];
    }

    private function somenteDigitos(mixed $valor): ?string
    {
        $digitos = preg_replace('/\D/', '', (string) $valor) ?? '';

        return $digitos === '' ? null : $digitos;
    }
}
