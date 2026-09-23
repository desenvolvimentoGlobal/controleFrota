<?php

declare(strict_types=1);

namespace App\Console\Commands\Concerns;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * Senha escolhida no console (usuario:criar e usuario:senha).
 *
 * ⚠️ No modo interativo a senha é digitada ÀS CEGAS (`secret`), nunca pelo
 * `--senha`: valor em opção de linha de comando fica no ~/.bash_history e
 * aparece no `ps` de qualquer usuário da máquina enquanto o comando roda. O
 * `--senha` existe para script, e avisa quando usado.
 *
 * A régua é a mesma da tela (`Password::defaults()`, definida no
 * AppServiceProvider): o console não é porta dos fundos da política de senha.
 */
trait EscolheSenha
{
    /**
     * @return string|false|null a senha escolhida; `null` = sortear uma
     *                           provisória; `false` = deu erro (já exibido)
     */
    private function senhaEscolhida(): string|false|null
    {
        $daLinha = $this->option('senha');

        if ($daLinha !== null) {
            $this->warn('⚠️  A senha veio pela linha de comando: ela fica no histórico do shell.');
            $this->warn('    Considere apagar a linha com `history -d` depois.');

            return $this->conferirForca((string) $daLinha);
        }

        if (! $this->input->isInteractive()) {
            return null;
        }

        if (! $this->confirm('Definir a senha agora? (não = o sistema sorteia uma provisória, com troca obrigatória)', false)) {
            return null;
        }

        $senha = (string) $this->secret('Senha (não aparece enquanto você digita)');

        if ($senha !== (string) $this->secret('Repita a senha')) {
            $this->error('As senhas não conferem. Nada foi alterado.');

            return false;
        }

        return $this->conferirForca($senha);
    }

    private function conferirForca(string $senha): string|false
    {
        $validador = Validator::make(['senha' => $senha], ['senha' => ['required', Password::defaults()]], [], ['senha' => 'senha']);

        if ($validador->fails()) {
            foreach ($validador->errors()->all() as $erro) {
                $this->error($erro);
            }
            $this->error('Nada foi alterado.');

            return false;
        }

        return $senha;
    }

    /** Provisória que passa na régua: letras e números, sem símbolo (fácil de ditar). */
    private function senhaProvisoria(): string
    {
        return Str::password(12, letters: true, numbers: true, symbols: false);
    }
}
