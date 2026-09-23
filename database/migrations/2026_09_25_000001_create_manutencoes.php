<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 3 — manutenção (docs/PLANEJAMENTO.md 3.4).
 *
 *  - `manutencoes`: planejada | preventiva | imediata; em_espera → em_prestacao → prestada (+ cancelada).
 *    Entrar em prestação põe o veículo em_manutencao; prestada devolve a disponivel.
 *  - `manutencao_movimentacoes`: linha do tempo imutável de tudo que aconteceu.
 *  - `manutencao_anexos`: orçamentos, notas fiscais, fotos (disco privado).
 *  - `planos_manutencao`: revisão periódica por km e/ou dias; o scheduler abre
 *    a manutenção quando o veículo se aproxima do limite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planos_manutencao', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('veiculo_id')->constrained('veiculos')->cascadeOnDelete();
            $table->string('nome', 120)->comment('Ex.: Troca de óleo e filtros');
            $table->unsignedInteger('intervalo_km')->nullable();
            $table->unsignedSmallInteger('intervalo_dias')->nullable();
            $table->unsignedInteger('ultimo_km')->nullable()->comment('Km da última execução');
            $table->date('ultima_data')->nullable()->comment('Data da última execução');
            $table->boolean('ativo')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('manutencoes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('veiculo_id')->constrained('veiculos')->restrictOnDelete();
            $table->string('tipo', 12)->comment('planejada|preventiva|imediata');
            $table->string('nome', 150);
            $table->text('descricao_problema')->nullable();
            $table->foreignId('fornecedor_id')->nullable()->constrained('fornecedores')->restrictOnDelete();
            $table->decimal('preco_previsto', 15, 2)->nullable();
            $table->decimal('preco_final', 15, 2)->nullable();
            $table->date('prazo')->nullable();
            $table->string('localizacao', 255)->nullable();
            $table->unsignedInteger('km_abertura')->nullable();
            $table->unsignedInteger('km_conclusao')->nullable();
            $table->string('situacao', 14)->default('em_espera')->index()->comment('em_espera|em_prestacao|prestada|cancelada');
            $table->json('sistemas')->nullable()->comment('Sistemas mecânicos tratados; voltam a OK na conclusão');
            $table->boolean('bloqueou_veiculo')->default(false)->comment('Pôs o veículo indisponível na abertura; libera na conclusão/cancelamento');
            $table->foreignId('aberta_por_id')->nullable()->constrained('usuarios')->nullOnDelete()->comment('Nulo = aberta pelo sistema (plano preventivo)');
            $table->foreignId('responsavel_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->foreignId('ocorrencia_id')->nullable()->constrained('ocorrencias')->nullOnDelete()->comment('Origem: ocorrência de checagem');
            $table->foreignId('plano_manutencao_id')->nullable()->constrained('planos_manutencao')->nullOnDelete()->comment('Origem: plano preventivo');
            $table->dateTime('inicio_prestacao_em')->nullable();
            $table->dateTime('concluida_em')->nullable();
            $table->string('motivo_cancelamento', 500)->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['veiculo_id', 'situacao']);
            $table->index('concluida_em');
        });

        Schema::create('manutencao_movimentacoes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('manutencao_id')->constrained('manutencoes')->cascadeOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->string('tipo', 20)->comment('abertura|situacao|alteracao|anexo|comentario');
            $table->string('descricao', 1000);
            $table->json('valores_antigos')->nullable();
            $table->json('valores_novos')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });

        Schema::create('manutencao_anexos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('manutencao_id')->constrained('manutencoes')->cascadeOnDelete();
            $table->string('titulo', 120)->nullable();
            $table->string('caminho');
            $table->string('nome_original')->nullable();
            $table->string('mime', 100)->nullable();
            $table->unsignedInteger('tamanho')->nullable();
            $table->foreignId('enviado_por_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manutencao_anexos');
        Schema::dropIfExists('manutencao_movimentacoes');
        Schema::dropIfExists('manutencoes');
        Schema::dropIfExists('planos_manutencao');
    }
};
