<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\EscolheSenha;
use App\Models\Perfil;
use App\Models\Usuario;
use App\Rules\Cpf;
use App\Services\AuditoriaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Cria um usuário pelo console — o PRIMEIRO acesso de uma instalação nova.
 *
 * Em produção o UsuarioSeeder não cria ninguém (senha conhecida em servidor
 * exposto é porta aberta), então o deploy sobe sem usuário e ninguém entra. A
 * saída seria `tinker` com `Usuario::create` à mão: sem auditoria, sem a régua
 * de senha e com chance de esquecer a troca obrigatória. É este comando.
 *
 * Senha definida agora (às cegas) → entra direto, porque quem cria é quem usa.
 * Senha sorteada → troca obrigatória no primeiro acesso (conta de outra pessoa).
 */
class CriarUsuarioCommand extends Command
{
    use EscolheSenha;

    protected $signature = 'usuario:criar
                            {--nome= : Nome completo}
                            {--login= : Login de acesso}
                            {--email= : E-mail}
                            {--cpf= : CPF (com ou sem máscara)}
                            {--perfil= : Código do perfil (admin, gestor, financeiro, geral)}
                            {--senha= : Define a senha em vez de sortear (evite no terminal: fica no histórico)}';

    protected $description = 'Cria um usuário (o primeiro acesso de uma instalação nova)';

    public function handle(AuditoriaService $auditoria): int
    {
        $codigos = Perfil::orderBy('id')->pluck('codigo')->all();
        if ($codigos === []) {
            $this->error('Não há perfis no banco. Rode antes: php artisan db:seed --force');

            return self::FAILURE;
        }

        $dados = [
            'nome' => trim((string) ($this->option('nome') ?: $this->ask('Nome completo'))),
            'login' => mb_strtolower(trim((string) ($this->option('login') ?: $this->ask('Login de acesso')))),
            'email' => trim((string) ($this->option('email') ?: $this->ask('E-mail'))),
            'cpf' => preg_replace('/\D/', '', (string) ($this->option('cpf') ?: $this->ask('CPF'))),
            'perfil' => $this->option('perfil') ?: $this->choice('Perfil', $codigos, array_search('admin', $codigos, true) ?: 0),
        ];

        // A mesma régua da tela (SalvarUsuarioRequest): sem ela o erro seria
        // uma violação de unique crua do banco no meio do deploy.
        $validador = Validator::make($dados, [
            'nome' => ['required', 'string', 'max:255'],
            'login' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9._-]+$/', Rule::unique('usuarios', 'login')],
            'email' => ['required', 'email', 'max:255', Rule::unique('usuarios', 'email')],
            'cpf' => ['required', 'digits:11', new Cpf, Rule::unique('usuarios', 'cpf')],
            'perfil' => ['required', Rule::in($codigos)],
        ], [], ['nome' => 'nome', 'login' => 'login', 'email' => 'e-mail', 'cpf' => 'CPF', 'perfil' => 'perfil']);

        if ($validador->fails()) {
            foreach ($validador->errors()->all() as $erro) {
                $this->error($erro);
            }

            return self::FAILURE;
        }

        $senha = $this->senhaEscolhida();
        if ($senha === false) {
            return self::FAILURE;
        }
        $provisoria = $senha === null;
        $senha ??= $this->senhaProvisoria();

        $perfil = Perfil::where('codigo', $dados['perfil'])->firstOrFail();
        $usuario = Usuario::create([
            'nome' => $dados['nome'],
            'login' => $dados['login'],
            'email' => $dados['email'],
            'cpf' => $dados['cpf'],
            'perfil_id' => $perfil->id,
            'senha' => $senha,
            'ativo' => true,
            'deve_trocar_senha' => $provisoria,
        ]);

        // Sem usuário logado a auditoria automática não grava: registra o ATO
        // (nunca a senha nem o hash).
        $auditoria->registrar('criou', 'usuarios', "Usuário {$usuario->login} criado pelo console (usuario:criar)", 'usuarios', $usuario->id, exigeUsuario: false);

        $this->newLine();
        $this->info("Usuário criado: {$usuario->nome} ({$usuario->login}) — perfil {$perfil->nome}.");

        if ($provisoria) {
            $this->warn('Senha provisória (anote agora — não será exibida de novo):');
            $this->line('    '.$senha);
            $this->line('A troca é obrigatória no primeiro acesso.');
        } else {
            $this->line('Senha definida por você. Entra direto, sem tela de troca.');
        }

        if ($perfil->codigo !== 'admin' && ! Usuario::whereHas('perfil', fn ($q) => $q->where('codigo', 'admin'))->where('ativo', true)->exists()) {
            $this->newLine();
            $this->warn('⚠️  Não há nenhum admin ativo no sistema: ninguém poderá criar usuários pela tela. Crie um admin.');
        }

        return self::SUCCESS;
    }
}
