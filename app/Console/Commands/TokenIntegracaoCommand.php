<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\TokenIntegracao;
use Illuminate\Console\Command;

/**
 * Gestão dos tokens da API de integração entre sistemas (2026-09-14).
 *
 * O token vive no banco, como no gestaoEmpresarial: trocar é recriar com o
 * mesmo nome — o hash antigo é substituído e o consumidor recebe o novo
 * texto. O texto claro aparece UMA vez, aqui; anote na hora.
 */
class TokenIntegracaoCommand extends Command
{
    protected $signature = 'integracao:token
                            {acao : criar, listar ou revogar}
                            {nome? : Nome do consumidor (ex.: emissao-os)}
                            {--escopos= : Permissões extras, separadas por vírgula (ex.: alocacoes)}';

    protected $description = 'Cria, lista ou revoga tokens da API de integração entre sistemas';

    public function handle(): int
    {
        return match ($this->argument('acao')) {
            'criar' => $this->criar(),
            'listar' => $this->listar(),
            'revogar' => $this->revogar(),
            default => $this->erroDeAcao(),
        };
    }

    private function criar(): int
    {
        $nome = (string) $this->argument('nome');

        if ($nome === '') {
            $this->error('Informe o nome do consumidor: integracao:token criar emissao-os');

            return self::FAILURE;
        }

        $escopos = $this->escoposInformados();

        if ($escopos === null) {
            return self::FAILURE;
        }

        $existia = TokenIntegracao::where('nome', $nome)->exists();
        $texto = TokenIntegracao::criarPara($nome, $escopos);

        $this->info(($existia ? 'Token RECRIADO' : 'Token criado')." para '{$nome}'.");
        $this->line('Escopos: '.($escopos === [] ? 'nenhum (só o básico)' : implode(', ', $escopos)));
        $this->warn('Anote agora — o texto não será exibido de novo:');
        $this->line($texto);

        if ($existia) {
            $this->warn('O token anterior deixou de valer. Atualize o consumidor.');
            // Recriar reescreve os escopos: quem trocar a senha sem repetir o
            // --escopos deixa o consumidor só com o básico, e é melhor que ele
            // saiba disso aqui do que pelo 403 em produção.
            $this->warn('Os escopos acima substituíram os anteriores.');
        }

        if (in_array(TokenIntegracao::ESCOPO_ALOCACOES, $escopos, true)) {
            $this->warn('ATENÇÃO: o escopo "alocacoes" expõe a agenda da frota com nomes de motoristas.');
        }

        return self::SUCCESS;
    }

    /**
     * Lê `--escopos`, recusando o que não existe. Escopo digitado errado não
     * pode virar "sem permissão" em silêncio: o consumidor só descobriria pelo
     * 403, e o operador acharia que tinha concedido.
     *
     * @return list<string>|null null = erro já reportado.
     */
    private function escoposInformados(): ?array
    {
        $bruto = (string) ($this->option('escopos') ?? '');

        if (trim($bruto) === '') {
            return [];
        }

        $escopos = array_values(array_filter(array_map('trim', explode(',', $bruto))));
        $invalidos = array_diff($escopos, TokenIntegracao::ESCOPOS);

        if ($invalidos !== []) {
            $this->error('Escopo desconhecido: '.implode(', ', $invalidos).'.');
            $this->line('Disponíveis: '.implode(', ', TokenIntegracao::ESCOPOS).'.');

            return null;
        }

        return $escopos;
    }

    private function listar(): int
    {
        $tokens = TokenIntegracao::orderBy('nome')->get();

        if ($tokens->isEmpty()) {
            $this->info('Nenhum token de integração cadastrado.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Consumidor', 'Escopos', 'Criado em', 'Último uso'],
            $tokens->map(fn (TokenIntegracao $t) => [
                $t->id,
                $t->nome,
                $t->escopos ? implode(', ', $t->escopos) : '—',
                $t->created_at?->format('d/m/Y H:i'),
                $t->ultimo_uso_em?->format('d/m/Y H:i') ?? 'nunca',
            ])->all(),
        );

        return self::SUCCESS;
    }

    private function revogar(): int
    {
        $nome = (string) $this->argument('nome');
        $token = TokenIntegracao::where('nome', $nome)->first();

        if ($token === null) {
            $this->error("Nenhum token com o nome '{$nome}'. Use integracao:token listar.");

            return self::FAILURE;
        }

        $token->delete();
        $this->info("Token de '{$nome}' revogado. O consumidor perde o acesso imediatamente.");

        return self::SUCCESS;
    }

    private function erroDeAcao(): int
    {
        $this->error('Ação desconhecida. Use: criar, listar ou revogar.');

        return self::FAILURE;
    }
}
