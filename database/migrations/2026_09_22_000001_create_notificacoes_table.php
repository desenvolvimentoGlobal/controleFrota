<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Notificações internas (sino do topbar). Padrão dos sistemas irmãos. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notificacoes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('usuario_id')->constrained('usuarios')->cascadeOnDelete();
            $table->string('tipo', 50)->default('geral');
            $table->string('titulo');
            $table->text('mensagem')->nullable();
            $table->string('url')->nullable();
            $table->timestamp('lida_em')->nullable();
            $table->timestamps();

            $table->index(['usuario_id', 'lida_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notificacoes');
    }
};
