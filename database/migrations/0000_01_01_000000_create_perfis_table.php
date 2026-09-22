<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Perfis de acesso (admin, financeiro, gestor, geral).
 * Criada antes de `usuarios`, pois usuarios.perfil_id referencia esta tabela.
 * Mesma convenção dos sistemas irmãos: coluna `codigo`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('perfis', function (Blueprint $table): void {
            $table->id();
            $table->string('nome', 50);
            $table->string('codigo', 30)->unique()->comment('admin | financeiro | gestor | geral');
            $table->string('descricao')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('perfis');
    }
};
