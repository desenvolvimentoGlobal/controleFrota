<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\EscolheSenha;
use App\Models\Usuario;
use App\Services\AuditoriaService;
use Illuminate\Console\Command;

/**
 * Redefine a senha pelo console. A tela exige estar logado — e a hora em que
 * a senha precisa ser trocada é justamente aquela em que ninguém entra.
 *
 * Sem este comando a saída seria um UPDATE pelo tinker, que passa por fora do
 * cast `hashed` (gravaria a senha em TEXTO PURO), não mexe na troca
 * obrigatória e não deixa registro de quem alterou a credencial de outra
 * pessoa.
 *
 * Cuidados: confirma mostrando o NOME (errar o login troca a senha da pessoa
 * errada); senha fraca não altera nada (a antiga continua valendo); avisa
 * quando a conta está inativa (senha nova ali não faz ninguém entrar).
 * Trocar a senha também derruba as sessões abertas da pessoa
 * (SessaoInvalidadaPelaSenha).
 */
class RedefinirSenhaCommand extends Command
{
    use EscolheSenha;

    protected $signature = 'usuario:senha
                            {login : Login ou e-mail do usuário}
                            {--senha= : Define a senha em vez de sortear (evite no terminal: fica no histórico)}
                            {--sem-troca : Não obriga a trocar a senha no próximo acesso}';

    protected $description = 'Redefine a senha de um usuário (quando ninguém consegue entrar)';

    public function handle(AuditoriaService $auditoria): int
    {
        $login = mb_strtolower(trim((string) $this->argument('login')));
        $usuario = Usuario::where('login', $login)->orWhere('email', $login)->first();

        if ($usuario === null) {
            $this->error("Nenhum usuário com o login ou e-mail \"{$login}\".");

            return self::FAILURE;
        }

        $this->line("Usuário: <info>{$usuario->nome}</info> ({$usuario->login}, {$usuario->email})");

        if (! $usuario->ativo) {
            $this->warn('⚠️  A conta está INATIVA: com senha nova ou não, ela não entra. Reative pela tela de usuários.');
        }

        if ($this->input->isInteractive() && ! $this->confirm("Trocar a senha de {$usuario->nome}?", false)) {
            $this->line('Nada foi alterado.');

            return self::FAILURE;
        }

        $senha = $this->senhaEscolhida();
        if ($senha === false) {
            return self::FAILURE;
        }
        $provisoria = $senha === null;
        $senha ??= $this->senhaProvisoria();

        $usuario->forceFill([
            'senha' => $senha,
            // Provisória sempre obriga a trocar; a definida só com --sem-troca não.
            'deve_trocar_senha' => $provisoria || ! $this->option('sem-troca'),
        ])->save();

        $auditoria->registrar('editou', 'usuarios', "Senha de {$usuario->login} redefinida pelo console (usuario:senha)", 'usuarios', $usuario->id, exigeUsuario: false);

        $this->newLine();
        if ($provisoria) {
            $this->warn('Senha provisória (anote agora — não será exibida de novo):');
            $this->line('    '.$senha);
        } else {
            $this->info('Senha redefinida.');
        }
        $this->line($usuario->deve_trocar_senha ? 'A troca é obrigatória no próximo acesso.' : 'Entra direto, sem tela de troca.');

        return self::SUCCESS;
    }
}
