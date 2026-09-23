<?php

declare(strict_types=1);

namespace App\Services\Integracao;

use App\Integracoes\GestaoPessoas;
use App\Models\Cargo;
use App\Models\Setor;
use App\Models\Usuario;
use App\Services\NotificacaoService;

/**
 * Traz do gestaoPessoas (fonte da verdade do RH) os dados dos usuários
 * VINCULADOS a uma ficha (`usuarios.colaborador_externo_id`):
 *  - nome, setor e cargo (setor/cargo criados aqui se ainda não existirem);
 *  - desligado no RH → inativado aqui, salvo se estiver com carro (aí o
 *    admin é avisado para resolver a alocação antes).
 *
 * Não cria usuário novo: a API do RH não expõe CPF nem e-mail, e login aqui
 * exige os dois. O vínculo é feito no cadastro do usuário.
 */
class SincronizarColaboradores
{
    public function __construct(
        private readonly GestaoPessoas $rh,
        private readonly NotificacaoService $notificar,
    ) {}

    /**
     * @return array{ok: bool, atualizados: int, inativados: int, bloqueados: int, sem_ficha: int, vinculados: int}
     */
    public function executar(): array
    {
        $resumo = ['ok' => false, 'atualizados' => 0, 'inativados' => 0, 'bloqueados' => 0, 'sem_ficha' => 0, 'vinculados' => 0];

        $colaboradores = $this->rh->colaboradores('todos');
        if ($colaboradores === null) {
            return $resumo;
        }

        $resumo['ok'] = true;
        $porId = collect($colaboradores)->keyBy('id');

        $usuarios = Usuario::whereNotNull('colaborador_externo_id')->get();
        $resumo['vinculados'] = $usuarios->count();

        foreach ($usuarios as $usuario) {
            $ficha = $porId->get($usuario->colaborador_externo_id);

            if ($ficha === null) {
                $resumo['sem_ficha']++;

                continue;
            }

            $dados = array_filter([
                'nome' => $ficha['nome'] ?? null,
                'setor_id' => $this->setorId($ficha['setor']['nome'] ?? null),
                'cargo_id' => $this->cargoId($ficha['cargo'] ?? null),
            ], fn ($v) => $v !== null && $v !== '');

            $usuario->fill($dados);
            if ($usuario->isDirty()) {
                $usuario->save();
                $resumo['atualizados']++;
            }

            if (($ficha['situacao'] ?? null) === 'desligado' && $usuario->ativo) {
                if ($motivo = $usuario->motivoParaNaoDesligar()) {
                    $resumo['bloqueados']++;
                    $this->notificar->enviar($this->notificar->comPerfis(['admin']), "rh_desligado_{$usuario->id}",
                        'Desligado no RH com veículo', "Desligado no Gestão de Pessoas, mas não inativado aqui: {$motivo}",
                        route('usuarios.show', $usuario, false), atualizarNaoLida: true);

                    continue;
                }

                $usuario->update(['ativo' => false]);
                $resumo['inativados']++;
                $this->notificar->enviar($this->notificar->comPerfis(['admin']), 'rh_desligado',
                    'Usuário inativado pelo RH', "{$usuario->nome} foi desligado no Gestão de Pessoas e inativado no Controle de Frota.",
                    route('usuarios.show', $usuario, false));
            }
        }

        return $resumo;
    }

    private function setorId(?string $nome): ?int
    {
        $nome = trim((string) $nome);

        return $nome === '' ? null : Setor::withTrashed()->firstOrCreate(['nome' => $nome], ['ativo' => true])->id;
    }

    private function cargoId(?string $nome): ?int
    {
        $nome = trim((string) $nome);

        return $nome === '' ? null : Cargo::withTrashed()->firstOrCreate(['nome' => $nome], ['ativo' => true])->id;
    }
}
