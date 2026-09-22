<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Frota. Três eixos de estado (docs/PLANEJAMENTO.md, 3.1):
 *  - `situacao`: operacional (disponivel, reservado, em_uso, em_manutencao, indisponivel, baixado);
 *  - `estado_inicial` / `estado_atual`: condição física (otimo..avariado);
 *  - `veiculo_condicoes`: um registro por sistema mecânico (ok/atencao/critico).
 * `veiculo_historico_estados` guarda toda mudança de situação, estado e km.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('veiculos', function (Blueprint $table): void {
            $table->id();

            // Identificação
            $table->string('nome', 80)->comment('Nome interno, ex.: "Onix branco 02"');
            $table->string('placa', 7)->unique()->comment('Maiúsculas sem hífen; ABC1234 ou ABC1D23');
            $table->char('chassi', 17)->nullable()->unique();
            $table->string('renavam', 11)->nullable();
            $table->string('foto_path')->nullable();

            // Descrição
            $table->string('marca', 60);
            $table->string('modelo', 60);
            $table->string('versao', 60)->nullable()->comment('Acabamento: LT, XEI, Comfortline...');
            $table->unsignedSmallInteger('ano_fabricacao');
            $table->unsignedSmallInteger('ano_modelo');
            $table->string('carroceria', 15)->nullable()->comment('hatch|sedan|suv|picape|minivan|utilitario|van|outro');
            $table->string('cor', 40)->nullable();
            $table->string('tipo_cor', 12)->nullable()->comment('solida|metalica|perolizada');
            $table->unsignedTinyInteger('portas')->nullable();
            $table->unsignedTinyInteger('lugares')->nullable();

            // Mecânica
            $table->string('motor', 20)->nullable()->comment('Ex.: 1.0, 2.0 Turbo');
            $table->unsignedSmallInteger('potencia_cv')->nullable();
            $table->string('combustivel', 12)->nullable()->comment('flex|gasolina|etanol|diesel|hibrido|eletrico|gnv');
            $table->string('cambio', 12)->nullable()->comment('manual|automatico|cvt|automatizado');
            $table->string('tracao', 12)->nullable()->comment('dianteira|traseira|integral');
            $table->string('direcao', 12)->nullable()->comment('mecanica|hidraulica|eletrica');

            // Conforto
            $table->string('ar_condicionado', 8)->nullable()->comment('nenhum|manual|digital');
            $table->string('bancos', 8)->nullable()->comment('tecido|couro|misto');
            $table->boolean('central_multimidia')->default(false);
            $table->boolean('pareamento_smartphone')->default(false);
            $table->boolean('painel_digital')->default(false);
            $table->boolean('vidros_eletricos')->default(false);
            $table->boolean('travas_eletricas')->default(false);

            // Segurança
            $table->boolean('abs')->default(false);
            $table->boolean('esc')->default(false);
            $table->boolean('sensor_ponto_cego')->default(false);
            $table->boolean('cinto_tres_pontos')->default(false);
            $table->boolean('isofix')->default(false);
            $table->string('airbags', 60)->nullable()->comment('Texto livre: "frontais e laterais", "6 airbags"');
            $table->unsignedTinyInteger('nota_latin_ncap')->nullable()->comment('0 a 5 estrelas');

            // Controle
            $table->string('situacao', 15)->default('disponivel')->index();
            $table->string('estado_inicial', 10)->comment('Condição física no cadastro; nunca muda');
            $table->string('estado_atual', 10)->index();
            $table->unsignedInteger('km_inicial')->default(0);
            $table->unsignedInteger('km_atual')->default(0);
            $table->date('data_aquisicao')->nullable();
            $table->decimal('valor_aquisicao', 15, 2)->nullable();
            $table->date('licenciamento_validade')->nullable();
            $table->date('seguro_validade')->nullable();
            $table->string('seguradora', 100)->nullable();
            $table->text('observacoes')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('veiculo_condicoes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('veiculo_id')->constrained('veiculos')->cascadeOnDelete();
            $table->string('sistema', 30)->comment('Chave de config/frota.php sistemas_mecanicos');
            $table->string('situacao', 10)->default('ok')->comment('ok|atencao|critico');
            $table->string('observacao', 500)->nullable();
            $table->foreignId('atualizado_por_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamps();

            $table->unique(['veiculo_id', 'sistema']);
        });

        Schema::create('veiculo_historico_estados', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('veiculo_id')->constrained('veiculos')->cascadeOnDelete();
            $table->string('campo', 20)->comment('situacao|estado_atual|km_atual');
            $table->string('valor_anterior', 20)->nullable();
            $table->string('valor_novo', 20);
            $table->string('origem', 15)->default('manual')->comment('manual|cadastro|alocacao|checagem|manutencao');
            $table->unsignedBigInteger('origem_id')->nullable();
            $table->string('observacao', 255)->nullable();
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamp('created_at')->nullable()->index();

            $table->index(['veiculo_id', 'campo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('veiculo_historico_estados');
        Schema::dropIfExists('veiculo_condicoes');
        Schema::dropIfExists('veiculos');
    }
};
