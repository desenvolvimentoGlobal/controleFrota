<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 2 — alocação, checagem por fotos e ocorrências (docs/PLANEJAMENTO.md 3.2 e 3.3).
 *
 * Decisão 22/09/2026: a validação da checagem é do PRÓPRIO motorista, que
 * compara cada item com a última foto do veículo. Anomalia vira `ocorrencia`
 * contra a alocação anterior (responsável presumido); o motorista anterior
 * contesta; o gestor revisa se quiser.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alocacoes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('veiculo_id')->constrained('veiculos')->restrictOnDelete();
            $table->foreignId('motorista_id')->constrained('usuarios')->restrictOnDelete();
            $table->foreignId('solicitante_id')->constrained('usuarios')->restrictOnDelete();
            $table->foreignId('aprovador_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->string('objetivo', 255);
            $table->string('destino', 255)->nullable();
            $table->dateTime('saida_prevista');
            $table->dateTime('retorno_previsto');
            $table->dateTime('saida_real')->nullable();
            $table->dateTime('retorno_real')->nullable();
            $table->unsignedInteger('km_saida')->nullable();
            $table->unsignedInteger('km_retorno')->nullable();
            $table->string('estado_saida', 10)->nullable()->comment('Condição física na saída');
            $table->string('estado_retorno', 10)->nullable()->comment('Condição física no retorno');
            $table->string('situacao', 12)->default('solicitada')->index()->comment('solicitada|aprovada|em_uso|concluida|recusada|cancelada');
            $table->dateTime('aprovada_em')->nullable();
            $table->string('motivo_recusa', 500)->nullable();
            $table->dateTime('atrasada_em')->nullable()->comment('Marcada pelo scheduler quando o retorno previsto passou');
            $table->text('observacoes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['veiculo_id', 'saida_prevista']);
            $table->index(['motorista_id', 'situacao']);
        });

        Schema::create('checagens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('alocacao_id')->constrained('alocacoes')->restrictOnDelete();
            $table->foreignId('veiculo_id')->constrained('veiculos')->restrictOnDelete()->comment('Redundante: busca da última do veículo');
            $table->foreignId('motorista_id')->constrained('usuarios')->restrictOnDelete();
            $table->string('tipo', 8)->comment('saida|retorno');
            $table->foreignId('checagem_anterior_id')->nullable()->constrained('checagens')->nullOnDelete()->comment('A que serviu de comparação');
            $table->unsignedInteger('km_informado')->nullable();
            $table->string('nivel_combustivel', 12)->nullable()->comment('vazio|quarto|meio|tres_quartos|cheio');
            $table->string('estado_geral', 10)->nullable()->comment('Condição física geral informada pelo motorista');
            $table->string('situacao', 10)->default('rascunho')->index()->comment('rascunho|concluida');
            $table->dateTime('concluida_em')->nullable();
            $table->string('observacao_motorista', 1000)->nullable();
            $table->foreignId('revisada_por_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->dateTime('revisada_em')->nullable();
            $table->string('observacao_revisao', 1000)->nullable();
            $table->timestamps();

            $table->unique(['alocacao_id', 'tipo']);
            $table->index(['veiculo_id', 'concluida_em']);
        });

        Schema::create('checagem_itens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('checagem_id')->constrained('checagens')->cascadeOnDelete();
            $table->string('categoria', 20);
            $table->string('item', 40)->comment('Chave de config/frota.php checagem.categorias.*.itens');
            $table->string('situacao', 10)->default('pendente')->comment('pendente|conforme|anomalia');
            $table->string('observacao', 500)->nullable();
            $table->timestamps();

            $table->unique(['checagem_id', 'item']);
        });

        Schema::create('checagem_fotos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('checagem_item_id')->constrained('checagem_itens')->cascadeOnDelete();
            $table->string('caminho')->comment('Disco privado: checagens/{checagem_id}/...');
            $table->string('nome_original')->nullable();
            $table->string('mime', 60)->nullable();
            $table->unsignedInteger('tamanho')->nullable();
            $table->foreignId('enviada_por_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->dateTime('apagada_em')->nullable()->comment('Retenção: arquivo removido, registro fica');
            $table->timestamps();
        });

        Schema::create('ocorrencias', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('veiculo_id')->constrained('veiculos')->restrictOnDelete();
            $table->foreignId('checagem_item_id')->constrained('checagem_itens')->cascadeOnDelete()->comment('Onde a anomalia foi apontada');
            $table->foreignId('checagem_item_anterior_id')->nullable()->constrained('checagem_itens')->nullOnDelete()->comment('Foto de comparação');
            $table->foreignId('alocacao_responsavel_id')->nullable()->constrained('alocacoes')->nullOnDelete()->comment('Responsável presumido');
            $table->foreignId('apontada_por_id')->constrained('usuarios')->restrictOnDelete();
            $table->string('descricao', 500);
            $table->string('situacao', 12)->default('aberta')->index()->comment('aberta|confirmada|descartada');
            $table->string('contestacao', 1000)->nullable();
            $table->dateTime('contestada_em')->nullable();
            $table->foreignId('revisada_por_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->dateTime('revisada_em')->nullable();
            $table->string('observacao_revisao', 1000)->nullable();
            $table->unsignedBigInteger('manutencao_id')->nullable()->comment('Fase 3: quando vira manutenção');
            $table->timestamps();

            $table->index(['veiculo_id', 'situacao']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ocorrencias');
        Schema::dropIfExists('checagem_fotos');
        Schema::dropIfExists('checagem_itens');
        Schema::dropIfExists('checagens');
        Schema::dropIfExists('alocacoes');
    }
};
