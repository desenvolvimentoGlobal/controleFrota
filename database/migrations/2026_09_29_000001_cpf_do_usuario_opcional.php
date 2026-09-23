<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CPF deixa de ser obrigatório no cadastro de usuário (pedido de 23/09/2026).
 * Continua único quando informado: o índice unique aceita vários NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table): void {
            $table->char('cpf', 11)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Só volta a NOT NULL se ninguém ficou sem CPF.
        Schema::table('usuarios', function (Blueprint $table): void {
            $table->char('cpf', 11)->nullable(false)->change();
        });
    }
};
