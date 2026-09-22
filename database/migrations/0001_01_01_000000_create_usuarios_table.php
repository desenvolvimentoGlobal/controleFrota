<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SUBSTITUI a migration padrão do Laravel (create_users_table).
 *
 * Decisão de projeto (22/09/2026): usuário e colaborador são UMA entidade.
 * Não há RH aqui, então a ficha (CPF, cargo, setor, contato, endereço) fica
 * junto do login. A hierarquia do perfil `gestor` sai de `gestor_id`.
 *
 * A CNH tem campos completos mas NÃO é obrigatória para dirigir: quem pode
 * pegar veículo é definido por `pode_dirigir`; CNH vencida só gera aviso.
 *
 * `password_reset_tokens` e `sessions` são tabelas INTERNAS do framework
 * (nomes/colunas em inglês de propósito).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuarios', function (Blueprint $table): void {
            $table->id();

            // Acesso
            $table->string('nome');
            $table->string('login', 50)->unique();
            $table->string('email')->unique();
            $table->string('senha');
            $table->foreignId('perfil_id')->constrained('perfis')->restrictOnDelete();
            $table->boolean('ativo')->default(true)->index();
            $table->boolean('deve_trocar_senha')->default(false);
            $table->dateTime('ultimo_login_em')->nullable();

            // Ficha
            $table->char('cpf', 11)->unique();
            $table->foreignId('setor_id')->nullable()->constrained('setores')->restrictOnDelete();
            $table->foreignId('cargo_id')->nullable()->constrained('cargos')->restrictOnDelete();
            $table->foreignId('gestor_id')->nullable()->constrained('usuarios')->nullOnDelete()
                ->comment('Quem responde por este usuário; base da visão do perfil gestor');
            $table->string('foto_path')->nullable();

            // Contato
            $table->string('telefone', 20)->nullable();
            $table->string('celular', 20)->nullable();

            // Endereço
            $table->char('cep', 8)->nullable();
            $table->string('logradouro')->nullable();
            $table->string('numero', 20)->nullable();
            $table->string('complemento', 100)->nullable();
            $table->string('bairro', 100)->nullable();
            $table->string('cidade', 100)->nullable();
            $table->char('uf', 2)->nullable();

            // Habilitação (não obrigatória — decisão 22/09/2026)
            $table->boolean('pode_dirigir')->default(false)->index();
            $table->string('cnh_numero', 20)->nullable();
            $table->string('cnh_categoria', 5)->nullable()->comment('A, B, AB, C, D, E...');
            $table->date('cnh_validade')->nullable();

            // Sincronia futura com o gestaoPessoas (fase 5)
            $table->unsignedBigInteger('colaborador_externo_id')->nullable()->unique();

            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('usuarios');
    }
};
