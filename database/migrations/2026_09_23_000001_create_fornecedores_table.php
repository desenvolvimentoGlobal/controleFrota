<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Fornecedores de serviços de manutenção. Cadastro próprio (decisão 22/09/2026), sem integração. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fornecedores', function (Blueprint $table): void {
            $table->id();
            $table->string('razao_social');
            $table->string('nome_fantasia')->nullable();
            $table->char('cnpj', 14)->nullable()->unique();
            $table->string('telefone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('contato', 100)->nullable()->comment('Pessoa de contato');
            $table->char('cep', 8)->nullable();
            $table->string('logradouro')->nullable();
            $table->string('numero', 20)->nullable();
            $table->string('complemento', 100)->nullable();
            $table->string('bairro', 100)->nullable();
            $table->string('cidade', 100)->nullable();
            $table->char('uf', 2)->nullable();
            $table->text('observacoes')->nullable();
            $table->boolean('ativo')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fornecedores');
    }
};
