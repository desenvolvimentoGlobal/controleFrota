<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Revisão de 23/09/2026: um veículo que o gestor tinha deixado INDISPONÍVEL
 * à mão saía DISPONÍVEL no fim da manutenção. A situação de antes da
 * prestação passa a ser guardada para ser respeitada na conclusão.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('manutencoes', 'situacao_antes_prestacao')) {
            return;
        }

        Schema::table('manutencoes', function (Blueprint $table): void {
            $table->string('situacao_antes_prestacao', 15)->nullable()->after('inicio_prestacao_em')
                ->comment('Situação do veículo ao iniciar a prestação (indisponível manual é restaurado)');
        });
    }

    public function down(): void
    {
        Schema::table('manutencoes', function (Blueprint $table): void {
            $table->dropColumn('situacao_antes_prestacao');
        });
    }
};
