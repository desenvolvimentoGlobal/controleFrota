<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Inscrições Web Push por dispositivo/navegador. Padrão dos sistemas irmãos. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inscricoes_push', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('usuario_id')->constrained('usuarios')->cascadeOnDelete();
            $table->string('endpoint', 500)->unique();
            $table->string('chave_p256dh');
            $table->string('chave_auth');
            $table->string('user_agent')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inscricoes_push');
    }
};
