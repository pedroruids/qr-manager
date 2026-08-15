<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qr_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('nome');
            // Único e imutável: é o que fica impresso no papel.
            $table->string('slug')->unique();
            $table->string('destino', 2048);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            // A listagem mostra os QRs de um utilizador, do mais recente para o
            // mais antigo.
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_codes');
    }
};
