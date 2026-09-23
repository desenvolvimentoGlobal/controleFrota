<?php

declare(strict_types=1);

namespace App\Http\Requests\Usuario;

use App\Models\Perfil;
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
        /** @var Usuario $atual */
        $atual = $this->user();

        // Quem não é admin não promove ninguém a admin e só pendura a pessoa
        // em alguém da própria cadeia. O formulário já esconde essas opções,
        // mas a regra vale no servidor: um POST montado à mão não passa.
        // O gestor dá só perfis que ele mesmo tem ou abaixo (geral, gestor):
        // "financeiro" abriria os custos da frota inteira. O perfil que a
        // pessoa já tem (dado por um admin) pode ser mantido.
        $perfisPermitidos = Perfil::query()
            ->when(! $atual->ehAdmin(), fn ($q) => $q->where(fn ($q2) => $q2->whereIn('codigo', ['geral', 'gestor'])
                ->when($usuario?->perfil_id, fn ($q3, $id) => $q3->orWhere('id', $id))))
            ->pluck('id')->all();
        $gestoresPermitidos = $atual->ehAdmin() ? null : [$atual->id, ...$atual->idsDaEquipe()];
        // Gestor direto precisa poder aprovar: quem recebe "aguardando
        // aprovação" e aloca para a equipe é gestor ou admin.
        $perfisQueGerem = Perfil::whereIn('codigo', ['admin', 'gestor'])->pluck('id')->all();

        return [
            'nome' => ['required', 'string', 'max:255'],
            'login' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9._-]+$/', Rule::unique('usuarios', 'login')->ignore($usuario)],
            'email' => ['required', 'email', 'max:255', Rule::unique('usuarios', 'email')->ignore($usuario)],
            'cpf' => ['required', 'digits:11', new Cpf, Rule::unique('usuarios', 'cpf')->ignore($usuario)],
            'perfil_id' => ['required', Rule::in($perfisPermitidos)],
            'senha' => [$usuario ? 'nullable' : 'required', 'string', Password::min(8)->letters()->numbers()],
            'setor_id' => ['nullable', 'exists:setores,id'],
            'cargo_id' => ['nullable', 'exists:cargos,id'],
            'gestor_id' => array_values(array_filter([
                'nullable', Rule::exists('usuarios', 'id')->whereIn('perfil_id', $perfisQueGerem)->whereNull('deleted_at'),
                $usuario ? Rule::notIn([$usuario->id, ...$usuario->idsDaEquipe()]) : null,
                $gestoresPermitidos !== null ? Rule::in($gestoresPermitidos) : null,
            ])),

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
            'colaborador_externo_id' => ['nullable', 'integer', 'min:1', Rule::unique('usuarios', 'colaborador_externo_id')->ignore($usuario)],
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
            'gestor_id.not_in' => 'O gestor não pode ser o próprio usuário nem alguém da equipe dele (criaria um ciclo).',
            'gestor_id.in' => 'Escolha como gestor você ou alguém da sua equipe.',
            'perfil_id.in' => 'Você não pode atribuir este perfil.',
            'gestor_id.exists' => 'O gestor escolhido precisa ter perfil de gestor ou administrador.',
            'colaborador_externo_id.unique' => 'Esta ficha do Gestão de Pessoas já está vinculada a outro usuário.',
        ];
    }

    private function somenteDigitos(mixed $valor): ?string
    {
        $digitos = preg_replace('/\D/', '', (string) $valor) ?? '';

        return $digitos === '' ? null : $digitos;
    }
}
