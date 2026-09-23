<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Placa e chassi eram únicos na tabela inteira: o veículo excluído (soft
 * delete) prendia a placa para sempre, e recadastrar um veículo excluído por
 * engano dava "já está em uso" apontando para um registro invisível. A
 * unicidade passa a ser só entre os não excluídos, validada no Form Request.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('veiculos', function (Blueprint $table): void {
            $table->dropUnique(['placa']);
            $table->dropUnique(['chassi']);
            $table->index('placa');
            $table->index('chassi');
        });
    }

    public function down(): void
    {
        Schema::table('veiculos', function (Blueprint $table): void {
            $table->dropIndex(['placa']);
            $table->dropIndex(['chassi']);
            $table->unique('placa');
            $table->unique('chassi');
        });
    }
};
