<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trilha de auditoria: quem fez o quê, quando, de onde. Registro IMUTÁVEL
 * (sem updated_at) e sem expurgo automático.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logs_auditoria', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->string('acao', 40)->index();
            $table->string('modulo', 60)->nullable()->index();
            $table->string('tabela', 60)->nullable();
            $table->unsignedBigInteger('registro_id')->nullable();
            $table->string('descricao', 500)->nullable();
            $table->json('valores_antigos')->nullable();
            $table->json('valores_novos')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->nullable()->index();

            $table->index(['tabela', 'registro_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logs_auditoria');
    }
};
