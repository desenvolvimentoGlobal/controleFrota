<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tokens da API de integração entre sistemas (`/api/integracao/v1`).
 * Mesmo desenho do gestaoPessoas/gestaoEmpresarial: só o HASH SHA-256 do
 * token fica no banco; escopos fechados por padrão.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tokens_integracao')) {
            return;
        }

        Schema::create('tokens_integracao', function (Blueprint $table): void {
            $table->id();
            $table->string('nome', 100)->unique()->comment('Consumidor, ex.: emissao-os');
            $table->char('token_hash', 64)->unique();
            $table->json('escopos')->nullable()->comment('Permissões extras; nulo = só o básico');
            $table->dateTime('ultimo_uso_em')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tokens_integracao');
    }
};
