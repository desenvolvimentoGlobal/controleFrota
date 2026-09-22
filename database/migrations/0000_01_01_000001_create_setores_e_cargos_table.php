<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cadastros auxiliares do usuário: setor e cargo. Tabelas simples com flag
 * `ativo` (padrão dos irmãos) — nunca se exclui um setor/cargo em uso.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('setores', function (Blueprint $table): void {
            $table->id();
            $table->string('nome', 100)->unique();
            $table->boolean('ativo')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('cargos', function (Blueprint $table): void {
            $table->id();
            $table->string('nome', 100)->unique();
            $table->boolean('ativo')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cargos');
        Schema::dropIfExists('setores');
    }
};
